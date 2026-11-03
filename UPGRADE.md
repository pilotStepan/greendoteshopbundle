# Upgrade

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

1. Apply `sql/purchase_date_created.sql` (MariaDB, safe to re-run): adds `date_created`, backfills it from
   `date_issue`, makes `date_issue` nullable and clears it for draft/cart/wishlist rows.
2. Clear the cache (API Platform metadata is cached).
3. Update your code:
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
4. Inquiry numbers are now built from `date_created` (`ManagePurchase::generateInquiryNumber()`). Thanks to the backfill,
   existing purchases keep the same number.

### Removed

- `DELETE /api/remove-variant/{productVariantId}` (`api_remove_order_item`) and `Service\PurchaseApiModel`: unused and
  broken. Drop `removeItem()` / `REMOVE_VARIANT` from your `assets/js/api/shop_api.js`.
- `POST /api/purchase/{id}/create-parcel` and `GET /api/purchase/{id}/parcel-status`: unused and without any access
  control. Parcels are created via `ManagePurchase::generateTransportData()` (`CreateParcelHandler`) and polled by
  `UpdateDeliveryStatusHandler`.

### Known, unchanged

- `PriceCalculator` still checks the `inquiry` place for transportation/payment prices.
