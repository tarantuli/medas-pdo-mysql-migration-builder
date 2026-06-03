# medas-pdo-mysql-migration-builder

Part of the [Medas framework](https://github.com/tarantuli/medas-core).

## Description

The MySQL driver for `medas-migration-builder`. Implements the `MigrationBuilder` interface for `Database` (MySQL) backends, generating `CREATE TABLE` and `ALTER TABLE` SQL statements from entity `Blueprint` definitions.

When `MakeMigrationCommand` processes an entity backed by a MySQL `Database`, this builder:

1. Reads the current table structure from `information_schema` via `TableStructureFinder`
2. Builds a `Blueprint` from the entity's attribute metadata
3. Runs `ChangeFinder` to diff the two
4. If the table doesn't exist: emits a `CREATE TABLE` statement via `CreateTableBuilder`
5. If the table exists but differs: emits `ALTER TABLE` statements via `AlterTableBuilder`
6. If the table matches the blueprint exactly: returns `false` (no migration needed)

Generated SQL is embedded into the `migrate()` method body as `Query` action objects, ready to be executed by `medas-storage-manager`'s migration runner.

## Usage

### Package developer context

Register the package — it automatically registers itself with `BuilderResolver` so `MigrationFactory` can find it:

```php
use Medas\PdoMysqlMigrationBuilder\PdoMysqlMigrationBuilderPackage;

PdoMysqlMigrationBuilderPackage::instance();
```

**Generating a migration:**

```bash
php bin/medas migration-builder:make-migration
```

For a new `Invoice` entity, this generates something like:

```php
$unitOfWork->addAction(new \Medas\PdoStorage\Queries\Query(
    <<<SQL
CREATE TABLE `invoices` (
    `id` BINARY(16) NOT NULL,
    `status` VARCHAR(255) NOT NULL DEFAULT '',
    `amount_cents` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL,
    `modified_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    [],
    service(\Medas\StorageManager\StorageManager::class)->byName("default"),
    \Medas\StorageManager\UnitOfWork\Priority::First
));
```

**Running generated migrations:**

```bash
php bin/medas storage-manager:migrate
```

### Backend user context

No additional configuration is required beyond `medas-pdo-mysql`. The builder is discovered and registered automatically. See `medas-migration-builder` for the `make-migration` command and `medas-storage-manager` for the migration runner.

**Limitations** — `AlterTableBuilder` handles adding columns and indices. Destructive changes (column renames, type changes, column drops) are not automatically detected and require manual SQL in a handwritten migration. Review generated migrations before running them in production.
