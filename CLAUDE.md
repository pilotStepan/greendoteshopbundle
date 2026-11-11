# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.
It is versioned and shared by everyone working on the bundle. Keep it free of machine- or person-specific
details, and update it in the same commit as any change to a command, path or workflow it describes.
Machine-specific notes go in `CLAUDE.local.md` (gitignored, optional, one per checkout), imported here:

@CLAUDE.local.md

## What this is

`greendot/eshopbundle` is a Symfony bundle (not a standalone app) that provides the shared e-commerce
backend (catalog, cart/purchase, payments, parcels, invoicing, exports, GA4 data layer, structured
data) for multiple tenant projects (e.g. `yogashop-24`). Every tenant app requires this bundle via
Composer and registers `Greendot\EshopBundle\GreendotEshopBundle` in `config/bundles.php`. All tenants
share the same entity/API schema so the proprietary multi-tenant CMS can consume every shop through an
identical data format. Tenants switch features with the bundle's config tree (`greendot_eshop.*`, see
`GreendotEshopBundle::configure()`) and change behavior by overriding or decorating bundle services in their
own `config/services.yaml`, never by forking entities.

This repo is a library, tested standalone with Composer, PHPUnit and Docker. To try an unreleased bundle
change inside a tenant project, set `LOCAL_BUNDLE_PATH` in the tenant's `.env.local` to this checkout and
run `php bin/console greendot:eshop:sync` there (`--watch` keeps syncing, `--clean` replaces the whole
`src/`; see `src/Command/SyncBundleCommand.php`). It copies files into the tenant's
`vendor/greendot/eshopbundle`; to go back to the released version, delete that directory and run
`composer install` in the tenant. Always
edit the bundle here, never in a tenant's `vendor/`.

## Commands

The bundle supports PHP >= 8.2. `composer test` and `composer phpstan` don't use your PHP: they run in the `php`
service of `compose.yaml` (PHP 8.2 with CI's extensions and ini values, image built on first use, rebuild with
`docker compose build php` after changing `docker/php/`), so a newer local PHP can't hide 8.2 failures or add
its own deprecations. Composer resolves dependencies for 8.2 too (`config.platform.php`), so `composer install`
and `composer update` work with any local PHP. Any other command on 8.2: `docker compose run --rm php <cmd>`.

```bash
composer install                 # install dependencies
composer test                    # run the full suite (Unit + Functional) on PHP 8.2, starting the test DB if needed
composer test -- --testsuite Unit # arguments after -- go to PHPUnit
composer test -- tests/Service/ManagePurchaseTest.php
composer test -- --filter testSomeMethodName
composer test:db:stop             # stop the test DB (otherwise it keeps running, also across reboots)
composer validate --strict         # what CI runs before installing deps
composer phpstan                   # static analysis on PHP 8.2 (PHPStan level 6, config: phpstan.dist.neon)
composer phpstan:baseline          # regenerate phpstan-baseline.neon (see rules below)
```

CI (`.github/workflows/tests.yml`) runs on PHP 8.2 against `master`/PRs: `composer validate --strict`,
`composer install`, `vendor/bin/phpstan analyse`, `vendor/bin/phpunit` with `TEST_DATABASE_URL` pointing at a MariaDB 10.5.29
service. It runs the tools directly, not through the Docker-based `composer test` / `composer phpstan`.

### Static analysis (PHPStan)
- `phpstan.dist.neon`: level 6 + `phpstan-doctrine` (validates DQL/QueryBuilder and entity mappings
  against a real EntityManager booted via `tests/phpstan/object-manager.php` / `ApiTestKernel`),
  `phpstan-symfony`, `phpstan-phpunit`. `phpVersion: 80200` = lowest supported PHP.
- `phpstan-baseline.neon` freezes the pre-existing errors (legacy debt). **New code must pass with zero
  new errors.** Never add entries to the baseline, `ignoreErrors`, or `@phpstan-ignore` to make an error
  go away — fix the cause. If a change is genuinely blocked, stop and ask.
- When you fix code that had baselined errors, the run fails with `ignore.unmatched` — that's expected:
  run `composer phpstan:baseline` so the baseline only shrinks, and include that diff in the change.
- Run `composer phpstan` before declaring any change done, alongside the relevant PHPUnit tests.

## Test architecture

- Everything under `tests/` except `tests/Functional` and `tests/App` (e.g. `tests/Controller`,
  `tests/Entity`, `tests/EventSubscriber`, `tests/Service`) →
  `Unit` testsuite (plain PHPUnit, some boot a minimal kernel). A new test directory must be added to
  the `Unit` suite in `phpunit.xml`, otherwise CI never runs it.
- `tests/Functional` → `Functional` testsuite; these are API-Platform HTTP tests.
- `tests/App/TestKernel.php` is a minimal kernel (FrameworkBundle only) for endpoint tests that don't
  need the full bundle wiring — register any controller it must expose in `tests/App/config/services.yaml`.
- `tests/App/ApiTestKernel.php` + `tests/App/ApiTestCase.php` boot a fuller kernel against **MariaDB
  10.5.29**, the production version, at `TEST_DATABASE_URL` (default in `phpunit.xml`; CI sets its own). There
  is no SQLite fallback: it hid SQL that production rejects. Each test class starts from a dropped and
  recreated schema (`SchemaTool` in `setUpBeforeClass()`); each test runs inside a transaction rolled back in
  `tearDown()` — don't rely on data surviving across tests. A default `Currency` row is created in `setUp()`
  because currency-aware listeners 500 without one. Locally the database is the `test-db` service in
  `compose.yaml` (Docker, data in memory, restarts with Docker). `composer test` starts it when it isn't
  running, so there is nothing to start or stop by hand. Host PHP can still reach it at 127.0.0.1:33061
  (`vendor/bin/phpunit`, the default URL in `phpunit.xml`), but that runs your PHP, not 8.2; without the
  database, functional tests stop with "No test database at ...". Any change to a repository, raw SQL, or
  DQL must be covered by a functional test. Don't mock repositories to test query logic.
- Entity fixtures use `zenstruck/foundry` factories under `tests/App/Factory/*Factory.php` — add new ones
  there rather than instantiating entities by hand in functional tests.
- `tests/Service/GreendotEshopBundleConfigurationTest.php` guards that every
  `#[Autowire(param: 'greendot_eshop.*')]` used anywhere in `src/` is actually defined in
  `GreendotEshopBundle::loadExtension()` — add the parameter there whenever you add such an `Autowire`.

## Architecture

### Bundle wiring
- `src/GreendotEshopBundle.php` is a Symfony `AbstractBundle`: `configure()` defines the
  `greendot_eshop` config tree (global URL, blog, mail, `shop.price.extension` discount strategy,
  `payment.rb_bank`, `parcel.{packeta,dpd,czech_post}`); `loadExtension()` turns that config into
  `greendot_eshop.*` container parameters consumed via `#[Autowire(param: '...')]`; `build()` registers
  attribute-based Doctrine ORM mapping for `src/Entity`; `prependExtension()` auto-prepends any YAML in
  `config/extensions/*.yaml` into the extension named by that file's root key (filename doesn't matter).
- `config/services.yaml` autowires everything under `src/*` except
  `DependencyInjection/Migrations/Tests/Kernel.php`; `src/Controller` gets
  `controller.service_arguments`.
- Tenant projects configure the bundle via `config/packages/greendot_eshop.yaml` using the same
  `greendot_eshop:` tree (see `yogashop-24` for a real example: enabling RB Bank + Packeta/DPD/Czech
  Post, setting the discount combination strategy, etc.).

### Domain entities
- Actual Doctrine entities live under `src/Entity/Project/*` (~90 classes: `Product`, `ProductVariant`,
  `Category`, `Purchase`, `Client`, `Transportation`, `Payment`, `Voucher`, `Upload`, `Review`, ...) — not
  directly in `src/Entity`, which only holds a few bundle-owned entities (`ExternalIds`,
  `PurchaseTracking`, `ResetPasswordRequest`). Repositories mirror this at `src/Repository/Project/*`,
  factories at `src/Factory/Project/*` (Doctrine persistence factories, distinct from the test-only
  Foundry factories in `tests/App/Factory`).
- Entities are translatable via Gedmo (`#[Gedmo\Translatable]`/`#[Gedmo\Versioned]`); string lookups by
  translated value go through `HintedRepositoryBase` (`src/Repository/HintedRepositoryBase.php`), whose
  `find*Hinted()` methods set the Gedmo `TranslationWalker` query hint for the current locale — use these
  instead of plain `findBy`/`findOneBy` when a criterion is a translatable field.
- Business enums live in `src/Enum` (e.g. `VatCalculationType`, `DiscountCalculationType`,
  `DiscountAmountType`, `TransportationAction`) and are validated directly in the bundle config tree.

### Schema changes
- Shop-table changes ship as bundle migrations: change the mapping and add
  `src/Migrations/Version<YYYYMMDDHHMMSS>.php` (namespace `Greendot\EshopBundle\Migrations`).
  `GreendotEshopBundle::prependExtension()` adds that directory to every tenant's `doctrine_migrations` config,
  so tenants need no config and must not copy the change into their own `migrations/`. Full guide:
  `docs/migrations.md`.
- Hand-written `addSql()` for MariaDB 10.5, safe to run twice (`IF NOT EXISTS` / `IF EXISTS`, `UPDATE`s whose
  `WHERE` matches nothing once applied), `isTransactional()` returns `false`. Tenant DBs drift and their
  `doctrine_migration_versions` misses changes applied by hand. Don't commit `doctrine:migrations:diff` output
  as-is. `tests/Functional/Migrations/BundleMigrationsTest.php` runs every migration twice on MariaDB.
- `sql/` holds the scripts from before bundle migrations, assumed applied everywhere. Don't add to it; nothing
  reads it.
- Every tenant DB gets the change, and so does the CMS, which also reads and writes tenant DBs **directly**
  (bypassing listeners and the workflow). Roll out in this order: run the migration on all tenant DBs (new
  columns nullable or otherwise harmless), then update the CMS, then enable the feature per tenant. Prefer
  extending existing tables over helper tables.
- Never rename or drop a column or serialized field in one step. Add the new one next to it (e.g. `getMoney()`
  next to `getPrice()`) and remove the old one only after every consumer has moved.

### Purchase invariants
- Prices, discounts and conversion rates depend only on `Purchase::getPricingDate()` (the issue date, or now),
  never on workflow places, which belong to tenants.
- `date_issue` is write-once. Only `Purchase::issue()` sets it: no setter and no API write group, not even for
  admins. `PurchaseIssueSubscriber` listens on `completed.checkout` / `completed.init_order` and must stay off
  the generic `completed` event.
- A purchase that must be issued but isn't is a bug: throw, don't fall back to another date.

### API Platform layer
- Entities carry `#[ApiResource]`/`#[ApiFilter]`/`#[Get]`/`#[GetCollection]`/... attributes directly
  (see `src/Entity/Project/Product.php`), often overriding the default provider/processor for a specific
  operation with a class from `src/StateProvider` or `src/StateProcessor` (e.g. `ProductStateProvider`
  backs `GET /products/filter`, `ProductItemStateProvider` backs the single-`Get`, `CartStateProcessor`
  and `PurchaseCheckoutProcessor` handle cart/checkout writes).
- Custom, reusable API-Platform filters live in `src/ApiResource` (e.g. `ProductSearchFilter`,
  `ProductParameterSearch`, `ProductFilterByDiscount`, `BoundingBoxFilter`) and are attached to entities
  via `#[ApiFilter(...)]`.
- `src/OpenApi/ShortCodeOpenApiFactory` decorates `api_platform.openapi.factory` to extend the generated
  OpenAPI spec.
- Serialization is controlled by `#[Groups(...)]` on entity properties — the same group names
  (`product_item:read`, `product_list:read`, `purchase:read`, etc.) are referenced from both entities and
  operation `normalizationContext`/`denormalizationContext`; when adding a field, decide which existing
  group(s) it belongs to rather than inventing a parallel one.
- `Purchase` sits close to API Platform's `eager_loading.max_joins` limit. A new relation in `purchase:read`,
  on `Purchase` or on an entity embedded in it, can make `GET /purchases/{id}` return a 500 ("total number of
  joined relations has exceeded the specified maximum"). Mark such relations
  `#[ApiProperty(fetchEager: false)]`, as `Purchase::$currency` and `PaymentType::$currency` already are.

### Cross-cutting subsystems
- **Payments**: `src/Payment/RbBank` (Raiffeisenbank statement import), `src/Invoice` (PDF invoice data
  assembly via `dompdf`), gateway abstractions under `src/Service/Payment*` and `GreendotEshopBundle`
  config (`payment.rb_bank`).
- **Parcels/carriers**: `src/Parcel` defines `ParcelServiceInterface` / `ParcelServiceProviderInterface`
  with concrete integrations in `src/Parcel/Integration` (Packeta/Zásilkovna, DPD, Czech Post/Balíkovna),
  async parcel creation and delivery-status updates go through Messenger messages/handlers in
  `src/Parcel/Message(Handler)`. Which carriers are active per tenant is config-driven
  (`greendot_eshop.parcel.*`).
- **Structured data (schema.org)**: `src/Schema` — `SchemaRegistry` dispatches to
  `SchemaProviderInterface` implementations per subject (`CatalogProductSchemaProvider`,
  `CatalogCategorySchemaProvider`, `ArticleSchemaProvider`, `FaqSchemaProvider`,
  `DefaultBreadcrumbSchemaProvider`, ...), rendered from Twig via `SchemaTwigExtension`. This is
  SEO/JSON-LD markup, unrelated to the multi-tenant CMS data-format contract.
- **GA4 data layer**: `src/DataLayer` — domain events (`ViewItemEvent`, `AddToWishlistEvent`,
  `CheckoutFunnelEvent`, `PurchaseEvent`, ...) are turned into GA4-shaped payloads by
  `src/DataLayer/Factory/*Factory` classes and consumed by `DataLayerEventListener`.
- **Purchase workflow**: `src/Workflow/PurchaseWorkflowContract` defines the Symfony Workflow places/
  transitions that drive purchase status; `PurchaseStateSubscriber*` (see `tests/EventSubscriber`) reacts
  to transitions (payment logging, etc.).
- **Search**: `src/Algolia` wraps `algolia/algoliasearch-client-php`; the bundle wires
  `Algolia\AlgoliaSearch\Api\SearchClient` from `%env(ALGOLIA_APP_ID)%`/`%env(ALGOLIA_API_KEY)%` in
  `config/services.yaml`, aliasing `ProductIndexerInterface` to `ProductIndexer`.
- **Feed exports**: `src/Export` (`ExportRegistry`, `ExportTypeInterface`, `GoogleProductsFeedType`) plus
  per-tenant export types layered in the consuming app's own `src/Export` (see `yogashop-24/src/Export/
  {Heureka,Zbozi,CustomGoogle}`).
- **i18n**: `src/I18n` (`ApiLocaleListener`, `RouteTranslator`, `TranslatedEntityValueResolver`) resolves
  locale for both routing and API requests.
- **Branch/store import**: `src/Command/{ImportBranchesCommand,SaveBranchesCommand}` plus
  `Service/Imports/Branch` sync physical store data.

### Consuming-app conventions (see `yogashop-24`)
- Tenants add their own `src/` tree mirroring the bundle's top-level namespaces (`ApiResource`,
  `Schema/Provider`, `Export`, `Security`, `Service`, `EventSubscriber`, ...) for tenant-specific code.
- To change bundle behavior, a tenant aliases the bundle's service id to its own class in
  `config/services.yaml` (one tagged service stays in place) or decorates it (`#[AsDecorator]`). It does not
  add parallel aliases and rewire config.
- Tenant config names bundle code directly: some bundle services get constructor arguments by name in
  `config/services.yaml` (e.g. `InvoiceMaker`), and bundle Doctrine filters are registered by class and by
  `DoctrineFiltersConfigNames` case in `config/packages/doctrine.yaml`. Removing or renaming such a parameter,
  class or enum case breaks every tenant's container, and no bundle test notices. Search all tenant configs first.
- Unused-looking public classes and public methods stay, even when nothing in the bundle calls them: tenants may.
  Remove them only if they cannot run at all (e.g. a repository whose entity class no longer exists, DQL on a
  field that doesn't exist). Other dead code (private methods, unused properties, imports and constructor
  dependencies) can go. List every removal in the change summary.
