# Localised images: shop bundle audit of upload file-field reads

`Upload` file fields (`Upload::FILE_FIELDS`: path, originalName, extension, mime, width, height) are `#[Gedmo\Translatable]`.
Gedmo translates them in postLoad for hydrated entities only. Scalar/array results and raw SQL need the
`TranslationWalker` hints (`HintedRepositoryBase::hintQuery()`), or must resolve the locale themselves.

Categories: **H** hydrated entity (OK), **W** walker-hinted query (OK), **R** raw/scalar without walker (must fix),
**D** intentionally default-locale.

Search used: `\.path|getPath\(\)|'path'|u\.path|upload\.path`, `getUpload()`, `Upload::class`, `upload_id`/`JOIN upload` in `src/` and `templates/`.

| Location | How it reads | Category | Action / reason |
|---|---|---|---|
| `src/Repository/Project/UploadRepository.php:85` (`getUploadForProduct`) | `SELECT DISTINCT u.path, u.name` array result | R, fixed to W | Query now goes through `hintQuery()`; repository extends `HintedRepositoryBase` |
| `src/Repository/Project/UploadRepository.php:185` (`findListingImagePathsForProducts`) | `u.path AS path` scalar result, feeds product listing image paths | R, fixed to W | `hintQuery()` applied before `getScalarResult()`; verified generated SQL is `COALESCE(translation.content, u.path)` |
| `src/StateProvider/ProductStateProvider.php:159` | Reads `path` from the rows above | W | Consumer of the fixed query, no change |
| `src/Algolia/ProductIndexer.php:158` | Raw SQL `LEFT JOIN upload u`, `u.path AS uploadPath` | R, fixed | `uploadPath` stays the default-locale path (backwards compatible); added `uploadPath_{locale}` per locale from `ext_translations` (same `{field}_{locale}` pattern as name/slug), falling back to the default path |
| `src/Export/Data/Factory/GoogleProductFeedFactory.php:59-67` | `getUpload()->getPath()` on lazily loaded relations; feed generated per locale | H, but wrong locale | Lazy proxies hydrate with the listener locale (CLI default), not the feed locale. Added `getLocalisedUploadPath()`: sets the upload's translatable locale and refreshes it when the feed has a locale |
| `src/Repository/Project/UploadRepository.php:115` | `$category->getUpload()?->getPath()` truthiness check | H | OK |
| `src/Repository/Project/UploadRepository.php` (`getCategoryUploads`, `getProductUploads`, `getProductWithVariantsUploads`, `findFirstImageUpload*`), `ProductRepository.php:54`, `ProductVariantRepository.php:64` | Queries returning `Upload` entities | H | OK, postLoad translates |
| `src/ApiResource/Product*Uploads.php`, `src/StateProvider/UploadProductWithVariantsProvider.php` | Entity collections via repository/QB | H | OK |
| `src/Entity/Project/ProductVariant.php:451` | `upload?->getPath()` | H | OK |
| `src/Controller/Shop/CategoryController.php:96` | `$category->getUpload()?->getPath()` | H | OK |
| `src/Schema/Builder/ProductSchemaBuilder.php:66,161,319`, `src/Schema/Provider/ArticleSchemaProvider.php:56-57` | `getUpload()->getPath()` | H | OK, follows request locale |
| `src/Service/GoogleXmlFeed.php:126,263` | `getUpload()->getPath()` | H | OK, legacy feed with no locale of its own, follows request locale |
| `src/Service/DownloadRequests/DefaultDownloadRequestHandler.php:27` | `getUpload()->getPath()` to attach a file | H | OK, follows the locale active when the request is handled |
| `src/Twig/AppExtension.php:313` (`getSizedImage`) | `Upload\|string` argument | H | OK, entity argument is hydrated; string argument comes from the caller |
| `src/Repository/Project/ProductRepository.php:127,133` | `u.path` only in a comment and in a `groupBy` with no join | n/a | Dead reference in `findTopSellingProducts`, not a path read; left untouched |
| `src/Factory/Project/UploadFactory.php:72` | Foundry fixture default | n/a | Test data |
| `templates/**` | No template reads upload `path` or other file fields (grep finds only `location.pathname` in `ad_click_capture.html.twig`) | n/a | Nothing to change; templates in consuming projects render hydrated entities (H) |
| `src/DataLayer/**` | No upload reads | n/a | Nothing to change |

Counts: H 12 rows (grouped), W/fixed-R 3, R fixed 1 (Algolia) + 1 feed-locale fix, D 0, deferred 0.

## Notes

- Admin-only reads of default-locale paths: none in this bundle (admin lives in the consuming project).
- Algolia consumers that want the localised image should read `uploadPath_{locale}`; `uploadPath` is unchanged.
- `Upload::getTranslatableLocale()` was added so the feed factory can skip a redundant refresh.
