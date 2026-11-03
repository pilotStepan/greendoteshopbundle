# Upgrade

## 7.4.1 – GoPay payment gateway

New card payment gateway `gopay` (`Service/PaymentGateway/GoPay`) next to GP webpay. GoPay sends the client back to
`/order/payment/gopay/return` and notifies the shop on `/order/payment/gopay/notify` (shared routes for every gateway
implementing `PaymentCallbackGatewayInterface`); payment state is always read from the GoPay API, never from the URL.
The gateway page opens in the purchase language (`PurchaseLocaleResolver`, same as order e-mails).

### Steps

1. Credentials in the project env: `GOPAY_CLIENT_ID`, `GOPAY_CLIENT_SECRET`, `GOPAY_GOID`. Keep `GOPAY_API_URL`
   empty: the sandbox is used in `dev`/`test`, the live gateway otherwise. Production credentials belong to the
   server's `.env.local`, never to the committed `.env`.
2. Optional config (`greendot_eshop.payment`):
   ```yaml
   greendot_eshop:
     payment:
       default_gateway: gopay                 # for payment types without payment_technical_action
       gopay:
         allowed_instruments: [PAYMENT_CARD, APPLE_PAY, GPAY]  # empty = every method enabled for the GoID
         default_instrument: PAYMENT_CARD     # the gateway opens straight on this method
   ```
   Values are checked against `GoPayPaymentInstrument`; a typo stops the container build.
3. Add `gateway.gopay` to the monolog channels you log to the DB.
4. **After the deploy** switch the card payment types: `UPDATE payment_type SET payment_technical_action = 'gopay'
   WHERE payment_technical_action = 'gpw';`. Keep the `GPWEBPAY_*` config: every gateway is still instantiated, and
   clients who started a GP webpay payment before the switch return to `/order/verify`.

## 7.4.0 – Purchase `date_created` / `date_issue`

A cart used to be priced at the date it was **created** (its `date_issue` was set in the constructor), so a cart left in
the session showed outdated prices. Now:

- **`date_created`** – when the purchase (cart, wishlist, draft, …) was created. Fixed, read-only.
- **`date_issue`** – when the purchase was issued (confirmed by the client). `NULL` until then. **Write-once**: no
  setter, not writable through the API (not even by admins), set only by `Purchase::issue()`, which never overwrites.
- Prices, conversion rates and delivery dates are resolved for **`Purchase::getPricingDate()`** =
  `date_issue ?? now`. Pricing never looks at workflow places.

The bundle issues the purchase on `completed` of `checkout` and `init_order` (`PurchaseIssueSubscriber`), i.e. only
after the transition fully succeeded and the new marking is stored, so a failed checkout never leaves a cart with an
issue date. Listeners that run in the `transition` phase (e.g. `PurchaseStateSubscriber::onReceive`) and listeners
on the generic `workflow.purchase_flow.completed` (dispatched before `completed.<transition>`) still see
`date_issue === null`. Listen on `completed.checkout` / `completed.init_order` when you need the date.

### Steps

1. **Before the deploy** apply `sql/purchase_date_created_1_schema.sql` (MariaDB, safe to re-run): adds
   `date_created`, backfills it from `date_issue` and makes `date_issue` nullable. The new mapping needs the column;
   apps on older bundles keep working with it.
2. Deploy, clear the cache (API Platform metadata is cached) and **restart the messenger workers** (a running worker
   keeps the old code and container).
3. **Right after the deploy** apply `sql/purchase_date_created_2_unissue.sql` (safe to re-run): clears `date_issue`
   for draft/cart/wishlist rows. Never run it while the app is still on an older bundle: older bundles declare
   `getDateIssue(): \DateTimeInterface` and throw a `TypeError` on `NULL`.
4. Update your code:
   - `Purchase::getDateIssue()` now returns `?\DateTimeInterface`. Handle `null`.
   - `Purchase::setDateIssue()` is removed. Don't set it on new carts/wishlists; nothing replaces it.
   - Carts, wishlists and drafts: show/sort by `dateCreated`. Orders: `dateIssue`.
   - **Twig**: `null|date` and `null|date_modify` do not fail, they render **today's** date. Check every
     `purchase.dateIssue` in templates: `dateCreated` for carts/drafts, `(purchase.dateIssue ?? purchase.dateCreated)`
     where both can occur.
   - Own transitions that should freeze prices (e.g. inquiries): call `$purchase->issue()` from a
     `workflow.purchase_flow.completed.<transition>` listener.
   - API clients: `date_issue` is no longer accepted on write; `date_created` is exposed in `purchase:read` and
     `purchase:wishlist`.
5. Inquiry numbers are now built from `date_created` (`ManagePurchase::generateInquiryNumber()`). Thanks to the backfill,
   existing purchases keep the same number.

### Mixed versions (shops sharing the simple-ws CMS)

The CMS has its own `Purchase` entity on the same `purchase` table, so its version has to match the shops it manages.

| CMS | Shop bundle | DB | Result |
|---|---|---|---|
| new | < 7.4.0 | part 1 missing | CMS fails on every purchase query (unknown column `date_created`) and on inserts (`date_issue` NOT NULL). |
| new | < 7.4.0 | part 1 applied | Drafts created in the CMS have `date_issue = NULL`; the old shop throws a `TypeError` when it touches them. |
| old | >= 7.4.0 | parts 1 + 2 | Works, but drafts created in the CMS get `date_issue` at creation (prices frozen too early) and admins can still write `date_issue`. |
| new | >= 7.4.0 | parts 1 + 2 | Target state. |

Rollout order: part 1 on every shop DB first → shops one by one (deploy, then part 2) → the CMS last.

A shop that stays on an older bundle behind the new CMS needs part 1 (never part 2) and a trigger that restores the
old "set on insert" behaviour for CMS inserts. Drop the trigger and run part 2 when that shop upgrades.

```sql
CREATE TRIGGER purchase_date_issue_legacy BEFORE INSERT ON purchase FOR EACH ROW
SET NEW.date_issue = COALESCE(NEW.date_issue, NOW());
```

Never run `doctrine:schema:update --force` on an app whose mapping doesn't know `date_created` yet: it drops the column.

### Removed

- `DELETE /api/remove-variant/{productVariantId}` (`api_remove_order_item`) and `Service\PurchaseApiModel`: unused and
  broken. Drop `removeItem()` / `REMOVE_VARIANT` from your `assets/js/api/shop_api.js`.
- `POST /api/purchase/{id}/create-parcel` and `GET /api/purchase/{id}/parcel-status`: unused and without any access
  control. Parcels are created via `ManagePurchase::generateTransportData()` (`CreateParcelHandler`) and polled by
  `UpdateDeliveryStatusHandler`.

### Known, unchanged

- `PriceCalculator` still checks the `inquiry` place for transportation/payment prices.
