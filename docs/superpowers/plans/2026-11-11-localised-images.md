# Localised Images Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Editors can give any image/file upload a per-language replacement file in the CMS; the shop shows it in that language and falls back to the default-language file otherwise.

**Architecture:** `Upload`'s file fields become Gedmo-translatable in both entity copies (bundle + simple-ws), so translations live in the existing `ext_translations` table and Gedmo fallback does the shop side. In the CMS one endpoint writes a "file set" for the current CMS locale (base columns in the default locale, translation rows otherwise); "connect existing" copies translations along.

**Tech Stack:** PHP 8 / Symfony / Doctrine ORM / gedmo/doctrine-extensions (stof), API Platform, Vue 3 (Encore), PHPUnit 11.

**Spec:** `docs/superpowers/specs/2026-11-11-localised-images-design.md` (bundle repo)

Repos: **B** = `/home/mates/Greendot/Projects/greendoteshopbundle`, **C** = `/home/mates/Greendot/Projects/simple-ws`. Each task names its repo; commit in that repo.

## Global Constraints

- File set = exactly `path`, `originalName`, `extension`, `mime`, `width`, `height`; always written and removed together.
- Default locale is never hardcoded: use `TranslatableListener::getDefaultLocale()` (C) / stof `default_locale` (B).
- `translation_fallback: true` stays on everywhere.
- No schema change, no `localisedFromPath`-style marker (user decision).
- No per-language hiding, no cleanup of existing upload texts, no localised versions for link uploads (`/uploads/link`).
- File fields are never machine-translated (DeepL) and never reported as "missing".
- Writing translations from code must pin the listener to the default locale around `TranslationRepository::translate()` (see `DuplicateProductProcessor::duplicateProductTranslations()` comment, C).
- Follow `docs/php-symfony-style-guide.md` (B).

## Review Focus

1. **Editor in EN mode uses "connect existing"** → copy's base columns hold the cs values, not EN ones. Test in Task 5.
2. **Localised file set is partial** (e.g. translated `path` but base `width`) after a write or a remove → never; all six are written/removed together. Tests in Task 6.
3. **SVG / non-image file replaced for EN** (`width`/`height` 0 or null) → stored as null translation-wise without breaking fallback of the other fields. Test in Task 6.
4. **"Remove EN version" called in the default locale** → rejected (400), base file untouched. Test in Task 7.
5. **Shop query that selects `u.path` as a scalar without the translation walker** → shows cs image on EN page. Covered by audit in Task 2.

---

### Task 1 (B): File fields translatable in bundle `Upload`

**Files:**
- Modify: `src/Entity/Project/Upload.php` (add `#[Gedmo\Translatable]` to `path`, `originalName`, `extension`, `mime`, `width`, `height`)
- Test: `tests/Entity/UploadTranslatableFieldsTest.php`

**Interfaces:**
- Produces: `Upload::FILE_FIELDS = ['path', 'originalName', 'extension', 'mime', 'width', 'height']` (public const).

- [ ] **Step 1: Write the failing test** `testFileFieldsAreGedmoTranslatable`: for each name in `Upload::FILE_FIELDS`, `(new \ReflectionProperty(Upload::class, $f))->getAttributes(\Gedmo\Mapping\Annotation\Translatable::class)` is non-empty. Also `testTextFieldsStayTranslatable` for `name`, `title`, `shortDescription`, `description`.
- [ ] **Step 2: Run** `vendor/bin/phpunit tests/Entity/UploadTranslatableFieldsTest.php` → FAIL (undefined constant).
- [ ] **Step 3: Add the const and the six attributes.**
- [ ] **Step 4: Run** same command → PASS; then `vendor/bin/phpunit --testsuite Unit` → no new failures.
- [ ] **Step 5: Commit** `feat(upload): make file fields translatable for localised images`.

### Task 2 (B): Audit raw path reads in the shop bundle

**Files:**
- Modify: whatever the audit finds (expected candidates: `src/Repository/Project/UploadRepository.php`, `src/StateProvider/UploadProductWithVariantsProvider.php`, `src/Export/Type/GoogleProductsFeedType.php`, `src/Algolia/**`, `src/DataLayer/**`, mail templates under `templates/`)
- Create: `docs/localised-images-shop-audit.md`

**Interfaces:** Consumes Task 1 mapping.

- [ ] **Step 1: List candidates**: `grep -rnE "\.path|getPath\(\)|'path'|u\.path|upload\.path" src templates` and every DQL/QueryBuilder on `Upload` or joining `upload`.
- [ ] **Step 2: Classify each** in the audit doc as one of: *hydrated entity* (OK, Gedmo postLoad), *walker-hinted query* (OK if it uses `HintedRepositoryBase` / `TranslationWalker` with current locale), *raw/scalar without walker* (must fix), *intentionally default-locale* (state why, e.g. admin-only).
- [ ] **Step 3: Fix each "must fix"** by adding the translation walker hints the same way `HintedRepositoryBase` does; per-locale feeds use the feed's locale.
- [ ] **Step 4: Run** `vendor/bin/phpunit` (both suites) → no new failures.
- [ ] **Step 5: Commit** `fix(upload): read localised image paths in raw queries` + audit doc.

### Task 3 (C): Gedmo mapping on CMS `Upload`

**Files:**
- Modify: `src/Entity/Project/Upload.php` — `implements Gedmo\Translatable\Translatable`; `#[Gedmo\Translatable]` on `name`, `title`, `shortDescription`, `description` + the six file fields; `#[Gedmo\Locale] private $locale;`; `setTranslatableLocale($locale): void`; `public const FILE_FIELDS` identical to Task 1.
- Test: `tests/Unit/Entity/Project/UploadTranslatableFieldsTest.php`

**Interfaces:** Produces `App\Entity\Project\Upload::FILE_FIELDS`.

- [ ] **Step 1: Write the failing test**: same assertions as Task 1, plus `assertInstanceOf(Translatable::class, new Upload())`.
- [ ] **Step 2: Run** `vendor/bin/phpunit tests/Unit/Entity/Project/UploadTranslatableFieldsTest.php` → FAIL.
- [ ] **Step 3: Implement the mapping.**
- [ ] **Step 4: Run** test → PASS; `php bin/console doctrine:schema:validate --skip-sync` → mapping OK; `php bin/console doctrine:schema:update --dump-sql --em=project` → **no SQL** (proves no schema change).
- [ ] **Step 5: Commit** `feat(upload): gedmo translatable mapping on Upload`.

### Task 4 (C): Keep file fields out of DeepL and the translation matrix

**Files:**
- Modify: `src/Service/Translation/EntityTranslator.php` (add `public const NON_TRANSLATABLE_FIELDS = Upload::FILE_FIELDS;` skip them like `SLUG_FIELDS` at line ~68)
- Modify: `src/Service/Translation/TranslationStatusService.php` — `getTranslatableFields()` filters out `EntityTranslator::NON_TRANSLATABLE_FIELDS` **only for `Upload::class`** (other entities may legitimately have a field named `name`/`path`)
- Test: `tests/Unit/Service/Translation/UploadFileFieldsExcludedTest.php`

**Interfaces:** Produces `TranslationStatusService::getTranslatableFields(Upload::class)` → `['name','title','shortDescription','description']`.

- [ ] **Step 1: Write the failing test** `testUploadFileFieldsAreNotOfferedForTranslation`: with a `TranslationStatusService` whose Gedmo config for `Upload::class` lists all ten fields (mock the config source the service reads), `getTranslatableFields(Upload::class)` equals the four text fields. `testOtherEntitiesUnaffected`: a config for another class containing `name` keeps it.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement the filter.**
- [ ] **Step 4: Run** → PASS; `vendor/bin/phpunit tests/Unit/Service/Translation` → green.
- [ ] **Step 5: Commit** `feat(translation): exclude upload file fields from machine translation`.

### Task 5 (C): Translation-aware upload copy ("connect existing")

**Files:**
- Create: `src/Service/Translation/TranslationCopier.php`
- Modify: `src/State/DuplicateProductProcessor.php` — `duplicateProductTranslations()` and `fetchDefaultLocaleValues()` delegate to the new service (keep `NAME_DERIVED_FIELDS` skip as the `$skipFields` argument)
- Modify: `src/Controller/CustomUploadApiController.php::copy()` (lines 42–85)
- Test: `tests/Unit/Service/Translation/TranslationCopierTest.php`

**Interfaces:**
- Produces:
  - `TranslationCopier::fetchDefaultLocaleValues(string $entityClass, int $id, array $fields): array<string,mixed>` (moved verbatim)
  - `TranslationCopier::copyTranslations(object $from, object $to, array $skipFields = []): void` (moved; pins listener to default locale, restores in `finally`)

- [ ] **Step 1: Write failing tests**:
  - `testCopyTranslationsWritesEveryLocaleAndField`: `findTranslations($from)` returns `['en' => ['path' => '/uploads/images/g-en.png', 'title' => 'Graph']]` → `translate($to, 'path', 'en', '/uploads/images/g-en.png')` and `translate($to, 'title', 'en', 'Graph')` called.
  - `testCopyTranslationsSkipsGivenFields`.
  - `testListenerLocaleRestoredAfterCopyEvenOnException`: active `en`, default `cs` → during translate locale is `cs`; afterwards `en`.
- [ ] **Step 2: Run** `vendor/bin/phpunit tests/Unit/Service/Translation/TranslationCopierTest.php` → FAIL.
- [ ] **Step 3: Implement the service, switch `DuplicateProductProcessor` to it.**
- [ ] **Step 4: Rewrite `copy()`**: for each source upload, take base values from `fetchDefaultLocaleValues(Upload::class, $id, [...10 translatable fields])` (empty array ⇒ current locale is default ⇒ getters are already base values), set them on the copy, persist + flush, then `copyTranslations($upload, $copy)`, flush.
- [ ] **Step 5: Run** `vendor/bin/phpunit` → green.
- [ ] **Step 6: Manual check** (CMS running): EN mode, connect an image that has an EN file to another product → new tile shows "EN version"; switch to CS → cs title/file on the copy.
- [ ] **Step 7: Commit** `fix(upload): connect-existing keeps translations and default-locale values`.

### Task 6 (C): File set value object + writer

**Files:**
- Create: `src/Model/UploadFileSet.php`
- Create: `src/Service/Upload/LocalisedUploadFileWriter.php`
- Modify: `src/Service/ImageService.php` (`processSendImagesToEndpointRequest`, ~line 452) and `src/Service/FileService.php` (`processSendFilesToEndpointRequest`, ~line 278): extract "build file set from the original model" into `ImageService::sendImagesAsFileSet(array $imageModels): ?UploadFileSet` / `FileService::sendFilesAsFileSet(array $fileModels): ?UploadFileSet`; existing methods keep their behaviour by calling these and creating the `Upload` from the set.
- Test: `tests/Unit/Service/Upload/LocalisedUploadFileWriterTest.php`, `tests/Unit/Model/UploadFileSetTest.php`

**Interfaces:**
- Produces:
  - `final readonly class UploadFileSet(string $path, string $originalName, string $extension, ?string $mime, ?int $width, ?int $height)`; `toArray(): array<string, scalar|null>` keyed by `Upload::FILE_FIELDS`; width/height `0` normalised to `null`.
  - `LocalisedUploadFileWriter::write(Upload $upload, UploadFileSet $set, string $locale): void` — default locale ⇒ setters on base columns; other locale ⇒ `translate()` for all six fields (listener pinned to default locale).
  - `LocalisedUploadFileWriter::remove(Upload $upload, string $locale): void` — deletes the six `ext_translations` rows for `(Upload::class, id, locale)`; throws `\LogicException` for the default locale.
  - `LocalisedUploadFileWriter::localisedLocales(Upload $upload): string[]` — locales with a translated `path`.

- [ ] **Step 1: Write failing tests**:
  - `UploadFileSetTest::testZeroDimensionsBecomeNull` (SVG case) and `testToArrayKeysMatchFileFields`.
  - `testWriteInNonDefaultLocaleTranslatesAllSixFields` (`translate` called exactly 6×, once per `FILE_FIELDS`, locale `en`; base `getPath()` unchanged).
  - `testWriteInDefaultLocaleSetsBaseColumns` (no `translate` call; getters return set values).
  - `testRemoveDeletesAllSixFieldRowsOnly` (query restricted to `FILE_FIELDS`; text translations untouched).
  - `testRemoveInDefaultLocaleThrows`.
  - `testLocalisedLocalesListsLocalesWithTranslatedPath`.
- [ ] **Step 2: Run** `vendor/bin/phpunit tests/Unit/Service/Upload tests/Unit/Model` → FAIL.
- [ ] **Step 3: Implement** VO, writer, and the two `…AsFileSet` extractions.
- [ ] **Step 4: Run** → PASS; full `vendor/bin/phpunit` green.
- [ ] **Step 5: Manual check**: upload a new image in the CMS as before → Upload row identical in shape to today's (regression of the refactor).
- [ ] **Step 6: Commit** `feat(upload): localised file set writer`.

### Task 7 (C): Endpoint for replacing / removing a file per language

**Files:**
- Modify: `src/Controller/CustomUploadApiController.php` — add two routes
- Test: `tests/Unit/Controller/CustomUploadApiControllerLocalisedFileTest.php`

**Interfaces:**
- Consumes: Task 6 writer and `…AsFileSet` methods.
- Produces:
  - `POST /upload/{id}/file` (multipart: `images[]` for image groups, `file` for ATTACHMENT/AUDIO/VIDEO; pick by `UploadGroup::getType()`; link uploads ⇒ 400). Locale = current request locale. Response 200 JSON `{ "locale": "en", "localisedLocales": ["en"], "otherLocaleVersions": [] }`; in the default locale `otherLocaleVersions` = `localisedLocales($upload)` (drives the "may need updating" warning).
  - `DELETE /upload/{id}/file` → 200 `{ "localisedLocales": [...] }`; default locale ⇒ 400 `"Výchozí jazyk nelze odebrat."`.

- [ ] **Step 1: Write failing tests** (controller unit test with mocked services): `testPostInEnWritesTranslation`, `testPostInDefaultLocaleReportsOtherLocaleVersions` (`localisedLocales` returns `['en','de']` ⇒ `otherLocaleVersions == ['en','de']`), `testDeleteInDefaultLocaleIs400`, `testLinkUploadIs400`.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement** both actions (thin: resolve upload, pick service, call writer, flush, respond).
- [ ] **Step 4: Run** → PASS.
- [ ] **Step 5: Commit** `feat(upload): replace/remove upload file per language`.

### Task 8 (C): Expose localisation state to the CMS UI

**Files:**
- Create: `src/Serializer/UploadLocalisationNormalizer.php` (decorates the API Platform item normalizer for `Upload`, groups `upload:read`)
- Test: `tests/Unit/Serializer/UploadLocalisationNormalizerTest.php`

**Interfaces:**
- Produces on every normalised `Upload` (group `upload:read`): `localisedLocales: string[]` (Task 6), `isMainImage: bool` (any of `getProducts()`, `getProductVariants()`, `getCategories()`, `getProducers()`, `getEvents()`, `getPeople()`, `getInformationBlocks()` non-empty).

- [ ] **Step 1: Write failing tests**: `testAddsLocalisedLocales`, `testIsMainImageTrueWhenUsedAsProductThumbnail`, `testIsMainImageFalseForPlainGalleryImage`, `testLeavesOtherClassesUntouched`.
- [ ] **Step 2: Run** → FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** → PASS.
- [ ] **Step 5: Commit** `feat(upload): expose localised locales and main-image flag`.

### Task 9 (C): CMS UI

**Files:**
- Modify: `assets/js/api/upload_api.js` — `uploadLocalisedFile(uploadId, file, isImage)`, `removeLocalisedFile(uploadId)`
- Modify: `assets/js/vue/components/galleryUploader/galleryTile.vue`, `galleryEditModal.vue`, `assets/js/vue/components/imageUploader/ImageUploaderViewTile.vue`, `assets/js/vue/components/thumbnail-preview.vue`, `assets/js/vue/components/mediaUploader/mediaUploaderRow.vue`

**Interfaces:** Consumes Task 7 routes, Task 8 fields, `window.appLocales.{current,default}`.

Behaviour (copy is Czech, the CMS language):
- Badge per locale in `localisedLocales`: `"EN verze"`.
- `current !== default`: button `"Nahradit pro EN"` (file picker → `uploadLocalisedFile`); if `localisedLocales` contains current: `"Odebrat EN verzi"` (confirm → `removeLocalisedFile`).
- `current === default`: button `"Nahradit soubor"`; after success, if `otherLocaleVersions` non-empty show warning toast `"Existují jazykové verze (EN, DE) – možná je bude potřeba také aktualizovat."`.
- `isMainImage`: hint `"Je to i hlavní obrázek – změna se projeví i tam."` next to the replace button.
- No buttons on link uploads.

- [ ] **Step 1: Implement** API helpers and components.
- [ ] **Step 2: Build** `yarn build` → no errors.
- [ ] **Step 3: Manual verification** (CMS + test shop): (a) EN mode, replace graph on a product gallery tile → badge "EN verze"; shop EN product page shows EN graph incl. `_sm` thumbnail size; CS page shows cs graph. (b) Locale with no version (e.g. DE) shows cs graph. (c) "Odebrat EN verzi" → EN page back to cs graph. (d) CS mode "Nahradit soubor" on that image → warning lists EN. (e) Thumbnail replaced in EN → listing tiles on EN shop use EN thumbnail.
- [ ] **Step 4: Commit** `feat(upload): localised image controls in CMS`.

---

## Release order

1. Bundle (Tasks 1–2) tagged and `composer update greendot/eshop-bundle` in each shop (yogashop-24 first).
2. CMS (Tasks 3–9) deployed after shops have the bundle; until then EN files saved in the CMS are simply ignored by old shops (harmless).
