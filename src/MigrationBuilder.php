<?php

declare(strict_types=1);

namespace Medas\PdoMysqlMigrationBuilder;

use Medas\Core\Attributes\Service;
use Medas\FileBuilder\PhpClass\MethodDefinition;
use Medas\MigrationBuilder\MigrationBuilder as MigraMigrationBuilder;
use Medas\MigrationBuilder\Structure\{Blueprint, Changes\ChangeFinder};
use Medas\PdoStorage\{Database, PdoStorageController, Queries\Query};
use Medas\StorageManager\{
    Interfaces\Storage,
    StorageManager,
    UnitOfWork\ActionSet,
    UnitOfWork\Priority
};

#[Service]
readonly class MigrationBuilder implements MigraMigrationBuilder
{
    public function __construct(
        private AlterTableBuilder    $alterTableBuilder,
        private ChangeFinder         $changeFinder,
        private CreateTableBuilder   $createTableBuilder,
        private PdoStorageController $pdoStorageController,
        private TableStructureFinder $tableStructureFinder,
    )
    {
    }

    public function handles(Storage $storage): bool
    {
        /** @noinspection PhpConditionAlreadyCheckedInspection */
        return $storage instanceof Database;
    }

    public function build(
        Storage|Database $storage,
        Blueprint        $expectedStructure,
        MethodDefinition $migrateMethod,
        MethodDefinition $undoMethod,
        bool             $ignoreExistingStructure = false,
    ): bool
    {
        $queries = $this->buildActions($storage, $expectedStructure, $ignoreExistingStructure);

        if (count($queries) === 0) {
            return false;
        }

        $queryClass = Query::class;
        $storageManagerClass = StorageManager::class;
        $priorityClass = Priority::class;

        foreach ($queries as $query) {
            $queryString = addcslashes(trim($query->query), '"');
            $storageName = addcslashes(trim($query->storage()->name()), '"');
            $migrateMethod->body .= <<<PHP
\$unitOfWork->addAction(new \\$queryClass(
    <<<SQL
$queryString
SQL,
    [],
    service(\\$storageManagerClass::class)->byName("$storageName"),
    \\$priorityClass::{$query->priority()->name}
));
PHP;
        }

        return true;
    }

    public function buildActions(
        Storage|Database $storage,
        Blueprint        $blueprint,
        bool             $ignoreExistingStructure = false,
    ): ActionSet
    {
        assert($storage instanceof Database);

        if ($ignoreExistingStructure) {
            $existingStructure = null;
        }
        else {
            $existingStructure = $this->tableStructureFinder->find($this->pdoStorageController->store(
                $blueprint->name,
                $storage
            ));
        }

        if ($existingStructure === null) {
            return $this->createTableBuilder->create($storage, $blueprint);
        }

        $changes = $this->changeFinder->find($blueprint, $existingStructure);

        return $changes
            ? $this->alterTableBuilder->create($storage, $blueprint, $changes)
            : new ActionSet();
    }
}
