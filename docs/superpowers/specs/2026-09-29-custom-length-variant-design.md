# Custom-length product variants

Date: 2026-09-29
Status: draft, awaiting review

## Goal

Let customers enter their own length for selected products instead of choosing from pre-generated variants. Example: a product sold in lengths 10 cm to 10 000 cm. Generating one variant per length is not feasible.

A customer can order several lengths of the same product in one purchase, and several pieces of each length (for example 2 × 2.5 m and 1 × 1 m).

## Decisions

| Topic | Decision |
|---|---|
| Unit | The length is an integer, 1 unit = 1 cm. The frontend handles display (metres, etc.). |
| Pricing | Tiered, whole-length (volume) pricing. The tier is chosen by the total length, and that tier's unit price applies to all of it. Example: 0–10 m at 100/m and 10 m+ at 80/m, so 25 m costs 25 × 80. Graduated (marginal) pricing is out of scope. |
| Pieces | `amount` stays the number of pieces. Length is separate. |
| Cap | Per-variant minimum and maximum length, enforced server-side. |
| Stock / availability | Ignore length. |
| Existing data | Unaffected. A null length means a normal line. |

## Design

### Data model

- `ProductVariant`: add nullable `lengthMin` and `lengthMax` (int, cm). A variant takes a custom length if and only if both are set. There is no separate boolean.
- `PurchaseProductVariant`: add nullable `length` (int, cm). This is the customer's chosen length and is stored on the order line, so old orders stay reproducible.
- `Price` is unchanged. For a length line, `minimalAmount` means "from N cm". The price is stored per cm.
- Add a Doctrine migration. Check how `sql/` is maintained in this repo and update it if it is.

### Validation

- A variant with a range requires a `length` within `lengthMin..lengthMax`. Otherwise reject with a 4xx and a clear message.
- A variant without a range rejects a supplied `length`.
- Validation is server-side, in the add and update cart paths. The frontend reads the same min and max from the product API for its input.

### Line identity

A cart line is identified by `(purchase, variant, length)`. Adding the same variant with the same length increases `amount`. A different length creates a new line. Currently lines are looked up by variant only, so this changes:

- `Service/ManagePurchase::addProductVariantToPurchase`: accept an optional `?int $length` and match on `(variant, length)`.
- `Controller/Shop/ProductController::addToCart` (about line 139): the "remove existing line then re-add" lookup must match `(variant, length)`. Otherwise changing one line wipes another. The route and request accept `length`.
- `ProductController` about line 214 (wishlist lookup): same treatment.
- `Controller/Shop/ClientSectionController` about line 375 (price preview): accept `length`.

### Price calculation

The seam is `Service/Price/ProductVariantPrice`, where a `PurchaseProductVariant`'s amount is read (about line 313).

For a line with a length:

1. Price **one piece** using the length as the pricing amount, so the tier is chosen by the length.
2. Multiply by the line's `amount` (pieces).

Unverified assumption: the existing tier loop in `ProductVariantPrice` (about lines 397–456) is a greedy "remaining amount" loop. It may not give pure volume pricing. First step of implementation is a test that pins the intended behaviour (25 m at the 10 m+ rate, as above). If the loop does not behave that way, add an explicit tier selection step for length lines only, without changing behaviour for normal lines.

### Output

- Expose `length` on the order line, and `lengthMin` and `lengthMax` on the product variant, in the relevant API serialization groups.
- Out of scope for the first pass: the length in DataLayer/GA4 and in exports. Follow up separately.

## Out of scope

- Graduated (marginal) tier pricing.
- Stock or availability depending on length.
- Fractional or sub-centimetre lengths.
- Auto-generated variants per length.

## Testing

- Tiered pricing: a length below the first tier, exactly on a threshold, and above the top tier.
- Pieces multiplier: `amount` > 1 at a given length.
- Two lengths of the same variant in one cart produce two lines, and each can be changed independently.
- The same length added twice merges into one line with a summed `amount`.
- Length rejected below `lengthMin`, above `lengthMax`, missing on a range variant, and supplied on a non-range variant.
- Normal (null length) lines behave exactly as before.

## Open questions

- Should the API here be versioned, if other frontends consume the cart endpoints? Adding an optional `length` is backward compatible, so likely not.
