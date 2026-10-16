# Custom-measure product variants

Date: 2026-09-29
Status: draft, awaiting review

Terminology: the field is called `measure` so it can carry any customer-entered quantity (length, width, weight, volume). It is an integer in the smallest unit for the product. For the first client, 1 = 1 cm. The frontend handles unit display.

## Goal

Let customers enter their own measure (for example a length) for selected products instead of choosing from pre-generated variants. Example: a product sold in lengths 10 cm to 10 000 cm. Generating one variant per length is not feasible.

A customer can order several measures of the same product in one purchase, and several pieces of each (for example 2 × 2.5 m and 1 × 1 m).

## Decisions

| Topic | Decision |
|---|---|
| Unit | The measure is an integer in the product's smallest unit (1 = 1 cm for the first client). The frontend handles display (metres, etc.). |
| Pricing | Tiered, whole-measure (volume) pricing. The tier is chosen by the entered measure, and that tier's unit price applies to all of it. Example: 0–10 m at 100/m and 10 m+ at 80/m, so 25 m costs 25 × 80. Graduated (marginal) pricing is out of scope. |
| Pieces | `amount` stays the number of pieces. Measure is separate. |
| Cap | Per-variant minimum and maximum measure, enforced server-side. |
| Stock / availability | Ignore measure. |
| Existing data | Unaffected. A null measure means a normal line. |

## Design

### Data model

- `ProductVariant`: add nullable `measureMin` and `measureMax` (int, smallest unit). A variant takes a custom measure if and only if both are set. There is no separate boolean.
- `PurchaseProductVariant`: add nullable `measure` (int, smallest unit). This is the customer's chosen measure and is stored on the order line, so old orders stay reproducible.
- `Price` is unchanged. For a measure line, `minimalAmount` means "from N units". The price is stored per unit (per cm for the first client).
- Add a Doctrine migration. Check how `sql/` is maintained in this repo and update it if it is.

### Validation

- A variant with a range requires a `measure` within `measureMin..measureMax`. Otherwise reject with a 4xx and a clear message.
- A variant without a range rejects a supplied `measure`.
- Validation is server-side, in the add and update cart paths. The frontend reads the same min and max from the product API for its input.

### Line identity

A cart line is identified by `(purchase, variant, measure)`. Adding the same variant with the same measure increases `amount`. A different measure creates a new line. Currently lines are looked up by variant only, so this changes:

- `Service/ManagePurchase::addProductVariantToPurchase`: accept an optional `?int $measure` and match on `(variant, measure)`.
- `Controller/Shop/ProductController::addToCart` (about line 139): the "remove existing line then re-add" lookup must match `(variant, measure)`. Otherwise changing one line wipes another. The route and request accept `measure`.
- `ProductController` about line 214 (wishlist lookup): same treatment.
- `Controller/Shop/ClientSectionController` about line 375 (price preview): accept `measure`.

### Price calculation

The seam is `Service/Price/ProductVariantPrice`, where a `PurchaseProductVariant`'s amount is read (about line 313).

For a line with a measure:

1. Price **one piece** using the measure as the pricing amount, so the tier is chosen by the measure.
2. Multiply by the line's `amount` (pieces).

Unverified assumption: the existing tier loop in `ProductVariantPrice` (about lines 397–456) is a greedy "remaining amount" loop. It may not give pure volume pricing. First step of implementation is a test that pins the intended behaviour (25 m at the 10 m+ rate, as above). If the loop does not behave that way, add an explicit tier selection step for measure lines only, without changing behaviour for normal lines.

### Output

- Expose `measure` on the order line, and `measureMin` and `measureMax` on the product variant, in the relevant API serialization groups.
- Out of scope for the first pass: the measure in DataLayer/GA4 and in exports. Follow up separately.

## Out of scope

- Graduated (marginal) tier pricing.
- Stock or availability depending on measure.
- Fractional or sub-unit values.
- Auto-generated variants per measure.

## Testing

- Tiered pricing: a measure below the first tier, exactly on a threshold, and above the top tier.
- Pieces multiplier: `amount` > 1 at a given measure.
- Two measures of the same variant in one cart produce two lines, and each can be changed independently.
- The same measure added twice merges into one line with a summed `amount`.
- Measure rejected below `measureMin`, above `measureMax`, missing on a range variant, and supplied on a non-range variant.
- Normal (null measure) lines behave exactly as before.

## Open questions

- Should the API here be versioned, if other frontends consume the cart endpoints? Adding an optional `measure` is backward compatible, so likely not.
