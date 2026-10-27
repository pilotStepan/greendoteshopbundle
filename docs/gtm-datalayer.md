# GTM / dataLayer events

How `greendot/eshopbundle` produces Google Tag Manager (GA4 e-commerce) `dataLayer` events, which parts the bundle
provides, which parts every project has to wire up itself, and how to do it.

Audience: developers and AI agents. Sections are self-contained; the [agent checklist](#11-agent-checklist) at the end
is a compressed version of the whole implementation guide.

---

## 1. Mental model (read this first)

```
            PHP side (bundle)                                         Browser
 ┌─────────────────────────────────────────────┐
 │ trigger                                     │
 │  • Twig function   {% do gtm_view_item() %} │
 │  • HTTP endpoint   GET /gtm/view-item/{id}  │
 │  • automatic       Doctrine onFlush /       │
 │                    purchase workflow        │
 │        │                                    │
 │        ▼ dispatch(Symfony event)            │
 │ DataLayerEventListener                      │
 │        │ calls Factory → builds DTO         │
 │        ▼                                    │
 │ DataLayerManager::push($data, $persist)     │
 │   persist=false → in-memory (this request)  │
 │   persist=true  → session key               │
 │                   _gtm_datalayer_events     │
 └────────┬───────────────────────┬────────────┘
          │                       │
 page render:                     JS: document.dispatchEvent(new CustomEvent('_gtm_trigger'))
 data_layer_push.html.twig        └─► fetch('/gtm/read/all') ─► DataLayerManager::all()
 calls gtm_get_events()                                          (drains session)
          │                       │
          ▼                       ▼
     window.dataLayer.push(event)  ──►  GTM container (tags/triggers/variables)
```

Key ideas:

1. **Events are produced server-side.** The browser never builds e-commerce payloads; it only asks the server to
   produce them and then reads the queue.
2. **`DataLayerManager` is a queue.** Events wait in memory or in the session until they are flushed to the browser —
   either inline when a page renders, or via `GET /gtm/read/all` after an AJAX action.
3. **`DataLayerManager::all()` drains the queue.** Every event is delivered exactly once — whichever comes first,
   the next page render or the next `/gtm/read/all` call.
4. **The bundle builds payloads; the project decides *when* to fire them.** Bundle = events, factories, DTOs,
   listener, manager, Twig functions, a few endpoints, the push template. Project = GTM snippet, including the push
   template, calling triggers on the correct pages / JS actions, the GTM container config.

---

## 2. File map

All paths relative to the bundle root (`vendor/greendot/eshopbundle/`).

| Layer | File | Role |
|---|---|---|
| Queue | `src/Service/DataLayer/DataLayerManager.php` | `push(array $data, bool $persist = false)` (adds the `ecommerce` reset), `all()` (returns + clears). Session key `_gtm_datalayer_events`. |
| Symfony events | `src/DataLayer/Event/*.php` | One class per trigger: `ViewItemEvent`, `ViewItemListEvent`, `ViewItemListProductEvent`, `PurchaseEvent`, `ModifyCart`, `CheckoutFunnelEvent`, `AddToWishlistEvent`, `PageViewEvent`. |
| Listener | `src/DataLayer/EventListener/DataLayerEventListener.php` | `#[AsEventListener]` per event → factory → `DataLayerManager::push()`. **The GA4 event names are defined here.** |
| Factories | `src/DataLayer/Factory/*.php` | Build payload DTOs from entities (prices, currency, categories, parameters). `DataLayerItemFactory` builds a single `items[]` entry; `FactoryUtilsTrait` has the category-tree helper. |
| DTOs | `src/DataLayer/Data/**` | Payload shapes. Plain public properties (serialized by `json_encode`), except `DataLayerItem` which implements `JsonSerializable`. |
| Category serialization | `src/DataLayer/Data/DataParseTrait.php` | Turns the category path into `item_category`, `item_category2`, … |
| Automatic cart/wishlist trigger | `src/EventSubscriber/CartModifiedListener.php` | Doctrine `onFlush` listener; diffs `PurchaseProductVariant` inserts/updates/deletes → `ModifyCart` or `AddToWishlistEvent`. |
| Automatic purchase trigger | `src/EventSubscriber/PurchaseStateSubscriber.php` | `onReceive` (workflow transition `T_CHECKOUT`) dispatches `PurchaseEvent`. |
| Twig functions | `src/Twig/GoogleTagManagerExtension.php` | `gtm_get_events`, `gtm_view_item`, `gtm_view_item_list`, `gtm_view_item_list_product`, `gtm_purchase`, `gtm_confirmation_purchase`, `gtm_view_cart`, `gtm_begin_checkout`, `gtm_add_shipping_info`, `gtm_add_payment_info`, `gtm_page_view`. |
| HTTP endpoints | `src/Controller/DataLayer/DataLayerApi.php` | Prefix `/gtm`: `read/all`, `checkout-funnel/{type}`, `view-item/{id}`. Routes are loaded by the bundle's `config/routes.yaml`. |
| Confirmation-page purchase | `src/Service/DataLayer/ConfirmationPurchaseTracker.php` + `templates/data_layer/purchase_push.html.twig` | Opt-in (`gtm.purchase_on_confirmation_page`): `purchase` is rendered by the thank-you page once per order instead of being queued. See [§7.7](#77-purchase). |
| Browser glue | `templates/data_layer/data_layer_push.html.twig` | Inline `<script>`: pushes queued events, listens for `_gtm_trigger`, debug overlay. Include as `@GreendotEshop/data_layer/data_layer_push.html.twig`. |
| Legacy (do not use) | `src/Service/GoogleAnalytics.php` + Twig `get_google_analytics_array()` in `src/Twig/AppExtension.php` | Old UA-era array builder. Not part of this system. |

---

## 3. Event catalogue

| `event` in dataLayer | PHP event | Who triggers it | Payload DTO (`ecommerce`) | Project work needed |
|---|---|---|---|---|
| `view_item_list` | `ViewItemListEvent(Category, ?productFetchUri, ?productIds)` | Twig `gtm_view_item_list(category, uri, ids)` **or** a project endpoint (bundle has none) | `ViewItemList` | Yes — call it on category/listing pages |
| `view_item_list_product` *(custom, not GA4)* | `ViewItemListProductEvent(Product)` | Twig `gtm_view_item_list_product(product)` | `ViewItemListProduct` (all variants of a product) | Yes — product detail page |
| `view_item` | `ViewItemEvent(ProductVariant)` | Twig `gtm_view_item(variant)` or `GET /gtm/view-item/{variantId}` | `ViewItem` | Yes — product detail / variant selection |
| `add_to_cart` | `ModifyCart` (type `add`) | **Automatic** (`CartModifiedListener` on flush) | `CartModified` | Only fire `_gtm_trigger` after the cart API call |
| `remove_from_cart` | `ModifyCart` (type `remove`) | **Automatic** | `CartModified` | Only fire `_gtm_trigger` |
| `add_to_wishlist` | `AddToWishlistEvent` | **Automatic** (purchase in workflow place `wishlist`) | `AddToWishlist` | Only fire `_gtm_trigger` |
| `view_cart` | `CheckoutFunnelEvent::ViewCart` | Twig `gtm_view_cart()` or `GET /gtm/checkout-funnel/view_cart` | `ViewCart` | Yes — cart page |
| `begin_checkout` | `CheckoutFunnelEvent::BeginCheckout` | Twig `gtm_begin_checkout()` or `GET /gtm/checkout-funnel/begin_checkout` | `BeginCheckout` | Yes — first checkout step |
| `add_shipping_info` | `CheckoutFunnelEvent::AddShippingInfo` | Twig / `GET /gtm/checkout-funnel/add_shipping_info` | `AddShippingInfo` | Yes — when shipping is chosen / step shown |
| `add_payment_info` | `CheckoutFunnelEvent::AddPaymentInfo` | Twig / `GET /gtm/checkout-funnel/add_payment_info` | `AddPaymentInfo` | Yes — when payment is chosen |
| `purchase` | `PurchaseEvent(Purchase)` | **Automatic** on checkout workflow transition; Twig `gtm_purchase(purchase)` exists for manual use | `Purchase` | Recommended: enable `gtm.purchase_on_confirmation_page` and include `purchase_push.html.twig` on the thank-you page ([§7.7](#77-purchase)) |
| — | `PageViewEvent(Category)` | Twig `gtm_page_view(category)` | — | **No listener exists — currently a no-op.** |

Before every event with an `ecommerce` key, `DataLayerManager::push()` adds `{"ecommerce": null}` (Google's
recommended reset) in the same storage as the event, so the reset survives delivery via `/gtm/read/all`. Don't push
the reset yourself; a bare `{"ecommerce": null}` pushed to the manager is ignored.

---

## 4. Payload shapes

Not pure GA4: besides GA4's `price` (unit price incl. VAT), **prices are also sent as `priceVat` and `priceNoVat`**
(and `valueVat` / `valueNoVat` on list/item events). All item prices are **per piece**; multiply by `quantity` for the
line total. See [§7.8](#78-gtm-container-configuration) for the container mapping.

### `items[]` entry (`DataLayerItem`)

```json
{
  "item_id": "1234",            // ProductVariant id (createFromVariant) or Product id (createFromProduct, list events)
  "item_name": "Yoga mat Pro",  // Product name
  "item_brand": "Producer name",// Product::getProducer()->getName(), "Unknown" if none
  "price": 1290.0,              // = priceVat, unit price
  "priceVat": 1290.0,
  "priceNoVat": 1066.12,
  "quantity": 1,
  "item_variant": "blue 183 cm",// view_item / view_item_list_product only
  "parameters": [{ "name": "Material", "values": ["TPE"] }], // same, non-variant parameters
  "index": 0,                   // view_item_list only
  "item_category": "Yoga",      // root category
  "item_category2": "Mats"      // … deeper levels, from the product's first category up the tree
}
```

Prices come from `ProductVariantPriceFactory` in the session currency (`CurrencyManager::get()`), priced for
`quantity` pieces (quantity discounts apply) and divided back to a unit price; `quantity` defaults to the variant's
minimal amount (or 1).

### Event-level shapes

```jsonc
// view_item, view_item_list_product
{ "currency": "CZK", "priceVat": 0, "priceNoVat": 0, "valueVat": 0, "valueNoVat": 0, "items": [ … ] }
// priceX/valueX = the cheapest non-zero item

// view_item_list
{ "item_list_id": "12", "item_list_name": "Mats", "valueVat": 0, "valueNoVat": 0, "items": [ … ] }
// valueX = sum over the listed products (from Product::getCalculatedPrices())

// add_to_cart, remove_from_cart, add_to_wishlist, view_cart, begin_checkout, add_shipping_info, add_payment_info
{ "currency": "CZK", "value": 0, "items": [ … ] }
// cart/wishlist: value = unit price × changed quantity; funnel: value = whole purchase with VAT

// purchase
{ "transaction_id": "5678", "value": 0, "tax": 0, "shipping": 0, "currency": "CZK",
  "customer_type": "new" | "returning", "items": [ … ] }
// shipping = transportation + payment price; "returning" = verified, non-anonymous client
// with another non-draft order in the last 540 days
```

Full dataLayer entry: `{ "event": "<name>", "ecommerce": { …payload… } }`. With
[Enhanced Conversions](#79-enhanced-conversions-google-ads) enabled, `purchase` also carries a top-level `user_data`.

---

## 5. Delivery: how events reach `window.dataLayer`

### 5.1 Page render (synchronous)

`data_layer_push.html.twig` calls `gtm_get_events()` → `DataLayerManager::all()` and prints one
`window.dataLayer.push(...)` per event. This covers:

- events dispatched by Twig functions **earlier in the same render**,
- events persisted to the session by previous requests that nobody read yet (e.g. `purchase` after the order redirect,
  `add_to_cart` from a non-AJAX form post).

> **Ordering rule:** a `{% do gtm_*() %}` call must run *before* the include of the push template is rendered,
> otherwise its event stays in the queue until the next request. That is why projects wrap the include in a
> `{% block gtm %}` and child templates call triggers before `{{ parent() }}`.

### 5.2 AJAX (asynchronous)

The push template registers:

```js
document.addEventListener('_gtm_trigger', async () => {
    const json = await (await fetch('/gtm/read/all')).json();
    json.forEach(e => window.dataLayer.push(e));
});
```

So after any JS action that produced events server-side (cart API call, `/gtm/view-item/...`, a project endpoint), the
frontend dispatches `_gtm_trigger` **after the request has finished**.

Only session-persisted events (`persist: true`) survive into the `/gtm/read/all` request — in-memory events die with
the request that created them.

### 5.3 Debug overlay

In the browser console:

```js
localStorage.setItem('debug_gtm', '1');   // enable, reload the page
localStorage.removeItem('debug_gtm');     // disable
```

Shows a fixed panel with live `window.dataLayer` contents (polls every 300 ms).

---

## 6. What the bundle gives you vs. what the project must do

| Bundle provides (nothing to do) | Project must implement |
|---|---|
| All event classes, factories, DTOs, listener | GTM container snippet + container id (env var) |
| Queue + session persistence | Including `data_layer_push.html.twig` in every layout (shop + checkout) |
| Automatic `add_to_cart`, `remove_from_cart`, `add_to_wishlist`, `purchase` | Dispatching `_gtm_trigger` after JS cart/wishlist actions |
| Twig functions for all page-level events | Calling Twig functions in the right templates (`{% block gtm %}` pattern) |
| `/gtm/read/all`, `/gtm/view-item/{id}`, `/gtm/checkout-funnel/{type}` | JS helpers calling those endpoints at the right UI moments |
| `ViewItemListEvent` + factory | An endpoint for `view_item_list` when products are loaded via JS (bundle has none) |
| — | GTM container config: variables mapping `priceVat` etc., tags, triggers |

---

## 7. Implementation guide (new project)

Examples are taken from a real project; adapt names and paths.

### 7.1 Prerequisites

- Bundle services and routes are loaded (`config/routes.yaml` of the bundle imports `src/Controller/`, which includes
  `DataLayerApi`). Verify: `bin/console debug:router | grep gtm_` → `gtm_read_all`, `gtm_checkout_funnel`,
  `gtm_view_item`.
- Sessions work for anonymous visitors (the queue and the cart both depend on it).
- `/gtm/*` must be reachable anonymously — check `access_control` in `security.yaml`.

### 7.2 GTM container id

`.env`:

```dotenv
GTM_KEY=GTM-XXXXXXX
```

`config/packages/twig.yaml`:

```yaml
twig:
    globals:
        gtm_key: '%env(GTM_KEY)%'
```

### 7.3 Layout(s): GTM snippet + push template

In **every** base layout that renders shop pages (often there is a separate checkout layout — do it there too):

```twig
<head>
    <!-- Google Tag Manager -->
    <script>
        (function (w, d, s, l, i) {
            w[l] = w[l] || [];
            w[l].push({'gtm.start': new Date().getTime(), event: 'gtm.js'});
            var f = d.getElementsByTagName(s)[0], j = d.createElement(s), dl = l != 'dataLayer' ? '&l=' + l : '';
            j.async = true;
            j.src = 'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
            f.parentNode.insertBefore(j, f);
        })(window, document, 'script', 'dataLayer', '{{ gtm_key }}');
    </script>
    <!-- End Google Tag Manager -->

    {# … stylesheets … #}

    {% block gtm %}
        {{ include('@GreendotEshop/data_layer/data_layer_push.html.twig') }}
    {% endblock %}
</head>
<body>
    <noscript>
        <iframe src="https://www.googletagmanager.com/ns.html?id={{ gtm_key }}"
                height="0" width="0" style="display:none;visibility:hidden"></iframe>
    </noscript>
```

If the project has a cookie-consent panel, it typically pushes its own event (e.g. `{event: 'cookies_accepted'}`);
consent handling is the project's/GTM container's job, not the bundle's.

### 7.4 Page-level triggers in Twig

Override `{% block gtm %}`, fire the trigger, then call `{{ parent() }}` (see the ordering rule in §5.1).

Product detail:

```twig
{% block gtm %}
    {% do gtm_view_item_list_product(product) %}     {# all variants of the product #}
    {# or, when a single concrete variant is shown: #}
    {# {% do gtm_view_item(product.productVariants|first) %} #}
    {{ parent() }}
{% endblock %}
```

Category / listing rendered server-side:

```twig
{% block gtm %}
    {% do gtm_view_item_list(category, null, products|map(p => p.id)) %}
    {{ parent() }}
{% endblock %}
```

`gtm_view_item_list` needs `productIds` **or** `productFetchUri` (throws otherwise). With a URI, the factory makes an
internal sub-request to `greendot_eshop.global.absolute_url ~ uri` and reads `id` from each element of the response
(supports API Platform's `member` wrapper).

Cart page:

```twig
{% block gtm %}
    {% if app.request.session.get('purchase') %}
        {% do gtm_view_cart() %}
    {% endif %}
    {{ parent() }}
{% endblock %}
```

> Always guard the funnel functions (`gtm_view_cart`, `gtm_begin_checkout`, …) when called without an argument: they
> look up the purchase via `PurchaseRepository::findOneBySession()` and a missing purchase causes a `TypeError`.

Thank-you page: include `purchase_push.html.twig`, see [§7.7](#77-purchase).

### 7.5 Frontend helpers

`assets/js/gtm/GtmEvents.js`:

```js
export function dispatchGtmTrigger() {
    document.dispatchEvent(new CustomEvent('_gtm_trigger'));
}
```

`assets/js/gtm/gtmTriggerApi.js` (adapt `fetchJson`/`postJson` to your HTTP helper):

```js
export const viewItem          = (variantId) => fetchJson(`/gtm/view-item/${variantId}`);
export const beginCheckout     = () => fetchJson('/gtm/checkout-funnel/begin_checkout');
export const addShippingInfo   = () => fetchJson('/gtm/checkout-funnel/add_shipping_info');
export const addPaymentInfo    = () => fetchJson('/gtm/checkout-funnel/add_payment_info');
export const viewCart          = () => fetchJson('/gtm/checkout-funnel/view_cart');
// project endpoint, see 7.6
export const viewItemListTrigger = (categoryId, productIds) =>
    postJson(`/gtm/view-item-list/${categoryId}`, { productIds });
```

Call sites — always *produce* first, *then* trigger the read:

```js
// add to cart / wishlist: the event is produced automatically by the cart API call
await addToCart(variant, amount);
dispatchGtmTrigger();

// variant picked on product detail
viewItem(selectedVariant.id).then(dispatchGtmTrigger);

// checkout steps (e.g. in a router watcher)
if (step === 'shipping') beginCheckout().then(dispatchGtmTrigger);
if (step === 'delivery') addShippingInfo().then(dispatchGtmTrigger);

// payment selected
await updateCart(addPaymentTypeToPurchase, id);
addPaymentInfo().then(dispatchGtmTrigger);

// product listing loaded via JS
viewItemListTrigger(categoryId, products.map(p => p.id)).then(dispatchGtmTrigger);
```

Batching is fine: several API calls can be followed by a single `dispatchGtmTrigger()` — `/gtm/read/all` returns
everything queued.

### 7.6 Project endpoint for `view_item_list` (JS-loaded listings)

The bundle has no HTTP endpoint for this event. Minimal project controller:

```php
namespace App\Controller\Gtm;

use Greendot\EshopBundle\DataLayer\Event\ViewItemListEvent;
use Greendot\EshopBundle\Entity\Project\Category;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/gtm')]
class CustomGtmController extends AbstractController
{
    #[Route('/view-item-list/{category}', name: 'view-item-list', methods: ['POST'])]
    public function viewItemList(
        #[MapEntity(mapping: ['category' => 'id'])] Category $category,
        Request $request,
        EventDispatcherInterface $eventDispatcher,
    ): JsonResponse {
        $productIds = json_decode($request->getContent(), true)['productIds'] ?? [];
        $eventDispatcher->dispatch(new ViewItemListEvent($category, null, $productIds));

        return new JsonResponse('dispatched', 200);
    }
}
```

The same pattern (dispatch a bundle event from a project route, then `_gtm_trigger` in JS) works for any event that
lacks a bundle endpoint.

### 7.7 Purchase

`PurchaseStateSubscriber::onReceive` dispatches `PurchaseEvent` on the checkout transition (usually inside the
checkout XHR). What happens next depends on one option.

**Recommended — confirmation page only** (`config/packages/greendot_eshop.yaml`):

```yaml
greendot_eshop:
    gtm:
        purchase_on_confirmation_page: true
```

`ConfirmationPurchaseTracker` only remembers the order id in the session (`_gtm_pending_purchases`). The thank-you
page renders the event:

```twig
{% block body_end %}
    {% include '@GreendotEshop/data_layer/purchase_push.html.twig' with {
        purchase: purchase,
        just_created: true,  {# page reached right after checkout / payment return, e.g. a ?created=1 flag #}
    } %}
{% endblock %}
```

It fires once per order: the id moves to `_gtm_sent_purchases` and the browser keeps a
`gtm_purchase_sent_<transaction_id>` localStorage flag (reloads, back button, opening the order later from the client
section). When the session was lost during the payment gateway round trip, `just_created: true` still lets the event
fire for an order issued in the last 24 hours. Orders without an order number (drafts) never fire. `just_created`
must be false on pages a customer can reopen at will without the session (e.g. an order detail opened from an email
link), otherwise the 24-hour fallback can count it on another device.

**Legacy (default, `false`)** — the event is persisted to the session queue and delivered by *whatever page or
`/gtm/read/all` call comes next*: the thank-you page if the user lands there directly, but just as well a checkout
step (Back from the payment gateway), the homepage or a product-page XHR. Nothing prevents duplicates across tabs.
Don't add `gtm_purchase()` on the thank-you page in this mode — that would double-count.

### 7.8 GTM container configuration

In the GTM UI (per project):

1. **Data Layer Variables** (Version 2) for fields that are not standard GA4: `ecommerce.value`, `ecommerce.valueVat`,
   `ecommerce.currency`, `ecommerce.transaction_id`, `ecommerce.tax`, `ecommerce.shipping`, `ecommerce.customer_type`,
   `ecommerce.items`, …
2. **Items price mapping:** `items[].price` is the unit price incl. VAT, consistent with `value`, so "Send Ecommerce
   data" works as is. If the shop reports net prices, map `priceNoVat` → `price` with a Custom JavaScript variable
   (or change the payload in the project, see §8.2) and do the same for `value`. Never multiply by `quantity` in the
   container: GA4 does that itself.
3. **Triggers:** Custom Event triggers named exactly as the `event` values in §3.
4. **Tags:** GA4 Event tags (or Google Ads / Meta / Sklik …) per trigger, with "Send Ecommerce data" from the data
   layer or the explicit variables above.
5. `view_item_list_product` is custom — map it to GA4 `view_item` in the container if needed, or ignore it.

### 7.9 Enhanced Conversions (Google Ads)

Enhanced Conversions sends the buyer's hashed email, phone and name with the conversion, so Google Ads can attribute
the purchase to an ad click even when the click cookie is missing (no consent, Safari, payment gateway round trip).
Opt-in:

```yaml
greendot_eshop:
    gtm:
        enhanced_conversions: true
```

`UserDataFactory` then adds `user_data` to the `purchase` event (both delivery modes of §7.7):

```jsonc
{
  "event": "purchase",
  "ecommerce": { … },
  "user_data": {
    "sha256_email_address": "…",        // Client::getMail()
    "sha256_phone_number": "…",         // Client::getPhone(), E.164
    "address": {                        // only when all four are known
      "sha256_first_name": "…",         // Client::getName()
      "sha256_last_name": "…",          // Client::getSurname()
      "postal_code": "11000",           // PurchaseAddress::getZip(), billing address
      "country": "CZ"                   // PurchaseAddress::getCountry()
    }
  }
}
```

- Values are normalized per Google's rules for pre-hashed data and hashed with SHA-256 **on the server**; plain
  contact data never reaches the page, so other scripts on it cannot read it.
  - email: trimmed, lowercased, dots removed from the local part of `gmail.com` / `googlemail.com`;
  - phone: E.164 (`+420739834430`); a number without `+`/`00` gets the calling code of the billing country
    (CZ, SK, PL, DE, AT, HU), otherwise it is left out;
  - names: trimmed, lowercased, digits and punctuation removed (diacritics kept).
- A field that is missing or cannot be normalized is left out; with no usable field, `user_data` is not sent.
- With `purchase_on_confirmation_page`, `user_data` is added only when this session placed the order. The 24-hour
  `just_created` fallback sends the purchase without it, because it cannot prove the viewer is the buyer.
- GTM: in the Google Ads Conversion Tracking tag tick "Include user-provided data from your website" and point it
  at a *User-Provided Data* variable of type *Code* returning the Data Layer Variable `user_data`. Enhanced
  Conversions must also be accepted in the Google Ads account (Goals → Settings).
- Google uses the data only when `ad_user_data` consent is granted (Consent Mode v2); the tags enforce that.
- Legal: mention in the privacy policy that hashed contact data of buyers is passed to Google for conversion
  measurement.

---

## 8. Extending and customizing

### 8.1 A new event in the bundle

1. `src/DataLayer/Event/MyEvent.php` — extends `Symfony\Contracts\EventDispatcher\Event`, readonly constructor props.
2. `src/DataLayer/Data/My/MyPayload.php` — public properties; `items` as `DataLayerItem[]`.
3. `src/DataLayer/Factory/MyFactory.php` — build items with `DataLayerItemFactory::createFromVariant()`/
   `createFromProduct()`, currency from `CurrencyManager::get()`.
4. `DataLayerEventListener` — new `#[AsEventListener(event: MyEvent::class)]` method:
   ```php
   $this->dataLayerManager->push(['event' => 'my_event', 'ecommerce' => $eventData], true);
   ```
   Use `persist: true` unless the event is only ever dispatched during a page render.
5. Trigger(s): `#[AsTwigFunction('gtm_my_event')]` in `GoogleTagManagerExtension` and/or a route in `DataLayerApi`.
6. Update this document (catalogue table + payload).

### 8.2 Project-specific events or payload changes

- **Own event:** inject `DataLayerManager` into any project service and `push(['event' => 'x', ...], true)`. No bundle
  change needed.
- **React to a bundle event:** add your own `#[AsEventListener(event: ViewItemEvent::class)]` in the project; it runs
  alongside the bundle listener.
- **Change a payload:** factories are plain autowired services with `protected` helpers. Extend the factory in the
  project and alias it, e.g. in `config/services.yaml`:
  ```yaml
  Greendot\EshopBundle\DataLayer\Factory\ViewItemFactory: '@App\DataLayer\ViewItemFactory'
  ```
  (`App\DataLayer\ViewItemFactory extends Greendot\EshopBundle\DataLayer\Factory\ViewItemFactory`.)

---

## 9. Gotchas and known limitations

- **`ecommerce` resets are automatic.** Events pushed straight to `window.dataLayer` by project JS (outside the
  manager) still need their own `{ecommerce: null}` before them, or GTM merges their `items[]` with the previous event.
- **Order of Twig calls matters** (§5.1). A trigger after the include renders on the *next* page.
- **Funnel Twig functions without a purchase throw** (§7.4) — guard with the session check.
- **`gtm_page_view` does nothing** — `PageViewEvent` has no listener.
- **`ViewItemListEvent` requires ids or URI** — passing neither throws `\Exception`.
- **`item_id` differs by event:** variant id for variant-based events, product id for `view_item_list`. Keep it in mind
  for GA4 item reports and remarketing feeds (which usually use variant ids).
- **Cart events come from Doctrine `onFlush`.** Any code path that flushes `PurchaseProductVariant` changes (admin
  edits, imports) also queues `add_to_cart`/`remove_from_cart` into the *current* session. Without an HTTP request
  (CLI commands, Messenger workers) `DataLayerManager::push(..., true)` calls `RequestStack::getSession()`, which throws
  `SessionNotFoundException` — keep that in mind when modifying purchase items outside a web request.
- **Wishlist vs. cart** is decided by `Purchase::hasPlace('wishlist')`; removals from a wishlist emit nothing.
- **Currency** is always the *session* currency (`CurrencyManager::get()`), not `Purchase::getCurrency()`; fallback
  `'CZK'`.
- **Queue is per session.** Two tabs share it; whichever tab reads first gets the events.
- **Legacy `GoogleAnalytics` service / `get_google_analytics_array()`** is unrelated old code — don't mix it in.

---

## 10. Verification

1. `bin/console debug:router | grep gtm` — bundle routes (+ project ones) exist.
2. Browser: `localStorage.setItem('debug_gtm','1')`, reload.
3. Walk the funnel and check that each event appears once, with non-empty `items` and sane prices:
   listing → `view_item_list`; product detail → `view_item_list_product` / `view_item`; add to cart → `add_to_cart`;
   cart → `view_cart`; checkout steps → `begin_checkout`, `add_shipping_info`, `add_payment_info`;
   order → `purchase` on the thank-you page, and not again after a reload or on the next page.
4. `/gtm/read/all` directly in the browser returns `[]` right after a page load (queue drained).
5. GTM Preview mode (Tag Assistant): the tags fire on the matching custom events.

---

## 11. Agent checklist

Implementing GTM on a project that uses `greendot/eshopbundle`:

- [ ] `GTM_KEY` in `.env`; Twig global `gtm_key`.
- [ ] GTM `<script>` in `<head>` + `<noscript>` iframe after `<body>` in **every** shop layout (incl. checkout layout).
- [ ] `{% block gtm %}{{ include('@GreendotEshop/data_layer/data_layer_push.html.twig') }}{% endblock %}` in `<head>` of
      every layout.
- [ ] `/gtm/*` accessible anonymously (`security.yaml`).
- [ ] Product detail template: `{% block gtm %}{% do gtm_view_item_list_product(product) %}{{ parent() }}{% endblock %}`
      (and/or `gtm_view_item(variant)`).
- [ ] Category/listing: `gtm_view_item_list(category, null, ids)` in Twig, or project `POST /gtm/view-item-list/{id}` +
      JS trigger when products load via JS.
- [ ] Cart template: guarded `gtm_view_cart()`.
- [ ] JS: `dispatchGtmTrigger()` helper; call it after every add-to-cart, remove-from-cart, add-to-wishlist request.
- [ ] JS: `begin_checkout` / `add_shipping_info` / `add_payment_info` via `/gtm/checkout-funnel/{type}` then
      `dispatchGtmTrigger()` at the matching checkout steps.
- [ ] Optional, after a legal check: `greendot_eshop.gtm.enhanced_conversions: true` + GTM Ads tag reads `user_data`
      (§7.9).
- [ ] `greendot_eshop.gtm.purchase_on_confirmation_page: true` + thank-you page includes
      `@GreendotEshop/data_layer/purchase_push.html.twig` (no manual `gtm_purchase`).
- [ ] Never call a `gtm_*` Twig function after `{{ parent() }}` inside `block gtm`.
- [ ] Verify with `debug_gtm` overlay (§10) and GTM Preview.
- [ ] Tell the client/marketing which payload fields exist (§4) so the GTM container maps `priceVat`/`priceNoVat`.
