# Upgrade notes

## Sequential order number (`purchase.order_number`)

Carts, wishlists and orders share `purchase.id`, so ids of real orders jump. Every real order now gets its own
sequential `order_number` at the `checkout` / `init_order` transition (`OrderNumberSubscriber`,
`PurchaseRepository::assignNextOrderNumber()`).

**Rule:** `id` stays for machines (URLs, API IRIs, `data-purchase`, fetch calls, logs, GP webpay `MERORDERNUM`, GA / dataLayer `transaction_id`).
`orderNumber` is for anything a human reads: emails, SMS, PDFs, client section, variable symbol, QR, carrier labels.

### Deploy (one deploy per project — code, migration and templates together)

1. Bump `greendot/eshopbundle`.
2. Add a Doctrine migration with the SQL from [`sql/purchase_order_number.sql`](sql/purchase_order_number.sql)
   (column + unique index `UNIQ_purchase_order_number` + backfill `order_number = id` for existing orders).
   Backfill keeps numbers already sent to customers valid, including the VS of unpaid proformas.
3. Update templates (below), then `cache:clear`.

Without the migration the app fails on the missing column — it never runs half-migrated silently.

### What changed in the bundle

| Where | Before | After |
|---|---|---|
| `InvoiceData` (PDF templates get its fields as variables) | `purchaseId` | **removed**; use `orderNumber` and `variableSymbol` |
| `OrderData` (email `data`) | `purchaseId` | `purchaseId` kept for links; new `orderNumber`, `variableSymbol` |
| Email / SMS subjects | `%id%` = id | `%id%` = order number (translations need no change) |
| QR payment `X-VS` | id | order number |
| RB bank import | `find(VS)` | `findOneBy(['orderNumber' => VS])` |
| Packeta `number`, DPD `reference(1)`, Czech Post `recordID`/`vsParcel`/`vsVoucher`/`note` | id | order number |
| `InvoiceMaker` `order_number` | id | order number |
| API `purchase:read` | — | `orderNumber` (read-only) |

`InvoiceData::purchaseId` was removed on purpose: a PDF template that was not updated renders an empty VS
(or errors in dev) instead of printing the id as a wrong variable symbol.

### Template checklist

```bash
grep -rn -E "purchase\.id|order\.id|purchaseId|Variabilní symbol|č\. objednávky|Proforma č\." templates assets/js
```

- Displayed number → `purchase.orderNumber` / `data.orderNumber` / `orderNumber` (PDF).
- Variable symbol → `variableSymbol` (PDF) / `purchase.orderNumber` (client section) — never `id`.
- Links, `path(..., {id: purchase.id})`, `data-purchase`, API calls → keep `id`.
- Carts (draft orders list) have no order number.
- Project-level PHP too: custom notification handlers (`#[AsTaggedItem(index: 'purchase_notification.*')]`) and anything
  building subjects / attachment names / VS from `$purchase->getId()` → `getOrderNumber()`.
  `grep -rn 'getId()' src | grep -i purchase`

### CMS

- Read `order_number` only when the tenant's DB has the column (`information_schema.COLUMNS`), otherwise fall back to `id`.
- Create orders through the project API / bundle workflow, so the number gets assigned. Raw DB writes must use the same
  `SELECT COALESCE(MAX(order_number), MAX(id), 0) + 1 FROM purchase FOR UPDATE` inside a transaction.
