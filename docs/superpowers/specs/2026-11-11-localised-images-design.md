# Localised images — design

Date: 2026-11-11
Scope: `greendot/eshop-bundle` (this repo), CMS `simple-ws`, verified against shop `yogashop-24`.

## Intent

Some images contain text, e.g. a product graph with Czech labels. On the English
version of the shop, the same slot must show an English version of the graph.
Editors must be able to do this inside the existing CMS workflow (switch the CMS
language, work on the product's images), without managing separate galleries per
language.

## Goals

1. An `Upload` (image or file) can have a **per-language replacement file**.
   A language without its own file **falls back** to the default-language (cs) file.
2. Works for every place uploads are used: gallery images, attachments, and the
   **main/thumbnail image** (`Product.upload`, `ProductVariant.upload`,
   `Category.upload`, `Producer.upload`, `Event.upload`, `Person.upload`,
   `InformationBlock.upload`).
3. The thumbnail is always present in every language — guaranteed by the fallback:
   a translation can only *replace* the file, never remove it.
4. Connecting an already-uploaded image to another object in the CMS
   ("connect existing") carries all its translations (file + texts) along.
5. Upload texts (`title`, `shortDescription`, `description`) become properly
   translatable in the CMS too (today they are not — see Current state).

## Non-goals

- **Hiding an image per language** (different gallery sets per locale). Explicitly
  deferred by the user. The design must not block it: a later non-translated
  `disabledLocales` list on `Upload` is the intended shape (a translated on/off
  flag would fall back wrongly — hidden in cs ⇒ hidden everywhere). Thumbnails
  would be excluded from hiding.
- Per-language gallery ordering. `sequence` stays shared.
- Physically deleting files from the shop server. Not done today either; out of scope.
- Cleaning up existing upload texts. EN-mode edits so far landed in the base (cs)
  columns; after the mapping change those values are treated as the cs source.
  The user decided to **leave them as they are** — no migration, no report.
- External link uploads (`/uploads/link`) — no localised version for now.

## Anti-goals (would make this a failure even if it "works")

- An English visitor sees **no image** where the Czech one exists (broken fallback,
  missing sized variants, translation lost on copy).
- DeepL auto-translation rewrites a file path into nonsense.
- An editor in EN mode accidentally overwrites the **Czech** file or texts.

## Current state (verified)

**Bundle (`src/Entity/Project/Upload.php`)** — implements `Gedmo\Translatable`;
`name`, `title`, `shortDescription`, `description` are `#[Gedmo\Translatable]`.
`path`, `originalName`, `extension`, `mime`, `width`, `height` are not.

**Shop (`yogashop-24`)** — stof config `default_locale: cs`, `translation_fallback: true`.
Templates read `upload.path` directly or via `sized_image(upload.path, '_sm')`
(`AppExtension::getSizedImage`, derives `<name>_<size>.<ext>` from `path`).
Entities loaded through relations get Gedmo's postLoad translation, so a translated
`path` reaches templates with no template changes.

**CMS (`simple-ws`)**
- Its own `src/Entity/Project/Upload.php` has **no Gedmo mapping at all** (unlike the
  bundle). Editing an upload's title in EN mode today writes the base (cs) column.
- `LocaleRequestSubscriber` sets the Gedmo listener locale to the editor's selected
  CMS language; other entities (Product, Category, …) are translated that way.
- Image upload: `CustomUploadApiController::images()` → `ImageService::processRequest()`
  → resizes into `<stub>-<uniqid>.<ext>` + `<stub>-<uniqid>_<size>.<ext>` variants,
  POSTs each to the shop's `/api/image-puller/save`, then creates a **new `Upload`**
  with `path = /uploads/images/<file>.<ext>` (`processSendImagesToEndpointRequest`).
- "Connect existing" (`connectExistingDialog.vue` → `copyUploadToObject` →
  `CustomUploadApiController::copy()`) **creates a new `Upload` row** per target
  object (an upload belongs to exactly one `UploadGroup`). It copies fields through
  getters, so (a) Gedmo translations are lost, (b) in EN mode it writes EN values
  into the copy's base columns.
- `DuplicateProductProcessor` already has the right pattern for (a)/(b):
  `fetchDefaultLocaleValues()` (reads base values via `IndexedTranslationWalker`) and
  `duplicateProductTranslations()` (copies `ext_translations` rows).
- `TranslatableResourceRegistry` auto-discovers any entity with `#[Gedmo\Translatable]`
  fields for the translation matrix, and `EntityTranslator` fills missing fields via
  DeepL. It already special-cases slug fields (`EntityTranslator::SLUG_FIELDS`).
- `UploadDistinctPathFilter` groups the "connect existing" search by base `path`.

## Design

### 1. Data model

On `Upload`, in **both** entity copies (bundle and simple-ws), mark these as
`#[Gedmo\Translatable]`:

- file fields: `path`, `originalName`, `extension`, `mime`, `width`, `height`
- text fields (simple-ws only; bundle already has them): `name`, `title`,
  `shortDescription`, `description`

simple-ws `Upload` gets `implements Translatable`, a `#[Gedmo\Locale]` property and
`setTranslatableLocale()`, matching the bundle.

No schema change: translations go to the existing Gedmo `ext_translations` table.
The "file set" for a locale is all six file fields together — they must always be
written and removed together (never a translated `path` with base `width`).

### 2. CMS: replacing the file for a language

- In the image tiles / edit modal (`galleryEditModal.vue`, `ImageUploaderViewTile.vue`,
  thumbnail preview, attachments rows), when the CMS language ≠ default language,
  show **"Replace for <LANG>"** and, if a translation exists, a badge
  ("EN version") and **"Remove <LANG> version"** (reverts to fallback).
- In the default language the existing behaviour stays: replacing / deleting acts
  on the base upload.
- New endpoint, e.g. `POST /upload/{id}/localised-file` (current CMS locale):
  runs the file through the same `ImageService` / `FileService` pipeline
  (resize + sized variants + send to image puller) **without creating a new
  `Upload`**, then writes the six file fields as translations for the current locale
  on the existing upload. `DELETE` on the same route removes those six translation rows.
- This requires splitting `processSendImagesToEndpointRequest()` so that "send files"
  and "create Upload entity" are separate steps.

### 3. Connect existing (copy) carries translations

Rework `CustomUploadApiController::copy()`:

- Read base values with the default-locale approach used in
  `DuplicateProductProcessor::fetchDefaultLocaleValues()`, not via getters in the
  current locale.
- After persisting the copy, duplicate all `ext_translations` rows of the source
  upload onto the copy's id (pattern: `duplicateProductTranslations()`). Extract the
  shared logic into a reusable service instead of a third copy.
- `DuplicateProductProcessor`, if it copies uploads, uses the same service.

### 4. DeepL / translation matrix

`Upload` will appear in the translation matrix automatically. File fields must be
**excluded** from `EntityTranslator` (never auto-translated, never shown as
"missing"), analogous to `SLUG_FIELDS`. Text fields (`title`, descriptions) stay
DeepL-translatable. `name` — builder decides (it is a file-name stub today).

### 5. Shop side (bundle)

- Entity relations: no change needed beyond the mapping (postLoad translation).
- Audit every place that reads upload paths **without hydrating `Upload` through
  Gedmo**: DQL/SQL selecting `u.path` as scalar, repositories not extending
  `HintedRepositoryBase`, `UploadRepository`, `UploadProductWithVariantsProvider`,
  `GoogleProductsFeedType` and other exports, Algolia indexing, DataLayer image URLs,
  mail templates (order confirmation images). Each must either go through the
  translation walker/hydration or be deliberately left at base locale (document which).
- Exports/feeds per locale should get the localised image where the feed is per-locale.

### 5b. Scope of upload kinds *(designer's call)*

- In scope: images (`ImageService`) and files sent via `FileService` — attachments,
  audio, video. For non-image files `width`/`height` are simply null in the file set.
- Out of scope for now: external links (`POST /uploads/link`, `UploadLinkPersister`)
  — no "Replace for <LANG>" button on link rows.

### 5c. Shared Upload rows *(designer's call)*

A thumbnail (`Product.upload` etc.) can be the same `Upload` row as a gallery image.
The localised file lives on the row, so replacing it for EN on the gallery tile also
changes the thumbnail in EN, and the other way round. That is intended: it is the
same picture, so it gets the same language version. The UI should say so when the
row is also used as a main image ("also the main image").

### 5d. Base file replaced → keep translations, warn *(user decision)*

When the default-language (cs) file of an upload is replaced, existing language
versions are **kept**, and the CMS shows a warning on the tile, e.g.
"EN version may be out of date". Nothing is removed automatically.

**No schema change and no stored staleness marker** (user decision — no
`localisedFromPath`-style column). The warning is therefore shown **at the moment of
replacing** *(designer's call)*: when the cs file is replaced, the CMS lists the
languages that have their own version ("EN, DE versions exist — they may need
updating too"). There is no persistent "out of date" badge afterwards; the
existing "EN version" badge still shows which languages have their own file.

Note: no base-file replace action was found in the CMS today (delete + re-upload
creates a new Upload without translations). If no replace flow exists, this
warning has nothing to hook into; the plan should confirm early and, if needed,
treat "replace base file" as a small addition.

### 6. Main image guarantee

Because translations only replace and fall back, the thumbnail always resolves in
every locale. The "Remove <LANG> version" action only removes the translation,
never the base file. No extra validation needed now; it becomes relevant with the
deferred hide feature.

## Constraints

- Two entity copies of `Upload` must stay mapping-compatible (same DB). Ship the
  bundle and simple-ws changes together; the shop must be on a bundle version with
  translatable file fields before editors start creating translations, otherwise the
  shop ignores them (harmless, just no effect).
- `translation_fallback: true` must remain on in every shop and in the CMS.
- Default locale is `cs` per stof config; CMS default comes from the project's
  default language (`LocaleRequestSubscriber`). Don't hardcode `cs`.
- Follow `docs/php-symfony-style-guide.md`.

## Left to the builder

- Exact UI placement and wording in the Vue components.
- Endpoint naming and whether it lives in `CustomUploadApiController` or a new controller.
- Whether `name` is translatable-with-DeepL or excluded.
- Test strategy (expected: unit tests for the translation-copy service, functional
  test for the localised-file endpoint, a bundle test that a translated `path`
  resolves in EN and falls back in a locale without one).
