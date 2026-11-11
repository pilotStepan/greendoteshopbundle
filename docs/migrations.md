# Schema migrations

Shop tables (`purchase`, `product`, ...) belong to the bundle, so their schema changes ship with the bundle as
Doctrine migrations in `src/Migrations/` (namespace `Greendot\EshopBundle\Migrations`).

The bundle adds that directory to `doctrine_migrations.migrations_paths` itself
(`GreendotEshopBundle::prependExtension()`). A project needs no config: the bundle's migrations show up in
`doctrine:migrations:list` next to the project's own `DoctrineMigrations\...`.

`sql/` holds the scripts written before this. They are assumed applied everywhere and nothing reads them.
Don't add new ones.

## Writing a migration

1. Change the mapping in `src/Entity/Project/`.
2. Add `src/Migrations/Version<YYYYMMDDHHMMSS>.php`:

   ```php
   namespace Greendot\EshopBundle\Migrations;

   use Doctrine\DBAL\Schema\Schema;
   use Doctrine\Migrations\AbstractMigration;

   final class Version20261201120000 extends AbstractMigration
   {
       public function getDescription(): string
       {
           return 'purchase.note: free-text note from the customer';
       }

       public function up(Schema $schema): void
       {
           $this->addSql('ALTER TABLE `purchase` ADD COLUMN IF NOT EXISTS `note` LONGTEXT NULL');
       }

       public function isTransactional(): bool
       {
           return false; // MariaDB commits DDL implicitly
       }
   }
   ```

3. Make it safe to run twice. Project databases drift, and some get a change by hand without recording it in
   `doctrine_migration_versions`. Use `ADD COLUMN IF NOT EXISTS`, `CREATE TABLE IF NOT EXISTS`,
   `CREATE INDEX IF NOT EXISTS`, `DROP ... IF EXISTS`, and give every `UPDATE` a `WHERE` that matches nothing
   once it has run. Production is MariaDB 10.5.
4. Write the SQL by hand. `doctrine:migrations:diff` compares the mapping with one database; use its output as
   a starting point at most.
5. Keep it compatible with the code that is still running: add nullable columns or columns with a default, and
   never rename or drop in the same release (add the new column, drop the old one later).
6. `tests/Functional/Migrations/BundleMigrationsTest.php` runs every bundle migration twice on MariaDB, on top
   of the schema the mapping creates. It runs with the rest of the suite (`composer test:db`, then
   `vendor/bin/phpunit`).

Don't copy the change into a project's own `migrations/`: the bundle migration already reaches every project.

## Running it in a project

Run the bundle migration on the project's database **before** deploying the bundle version whose mapping needs
it.

```bash
php bin/console doctrine:migrations:list                 # pending ones show "not migrated"
php bin/console doctrine:migrations:execute --up 'Greendot\EshopBundle\Migrations\Version20261201120000'
```

`execute --up` runs that one migration and records it. Prefer it over `doctrine:migrations:migrate` until
the project's `doctrine_migration_versions` matches what was really applied: `migrate` runs every migration
listed as "not migrated", including old project migrations that were applied by hand and never recorded.

The CMS reads and writes project databases directly, so roll out in this order: run the migration on every
project database, then update the CMS, then enable the feature per project.
