<?php

declare(strict_types=1);

namespace Medas\PdoMysqlMigrationBuilder;

use Medas\Core\Attributes\Service;
use Medas\FileBuilder\PhpClass\MethodDefinition;
use Medas\PdoStorage\{Database, PdoStorageController, Queries\Query};
use Medas\StorageManager\Interfaces\Storage;
use Medas\StorageManager\StorageManager;
use Medas\MigrationBuilder\Structure\{Blueprint, Changes\ChangeFinder};
use Medas\StorageManager\UnitOfWork\{ActionSet, Priority};

#[Service]
readonly class MigrationBuilder implements \Medas\MigrationBuilder\MigrationBuilder
{
    public function __construct(
        private AlterTableBuilder    $alterTableBuilder,
        private ChangeFinder         $changeFinder,
        private CreateTableBuilder   $createTableBuilder,
        private PdoStorageController $pdoStorageController,
    )
    {
    }

    public function build(
        Storage          $storage,
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
        Storage   $storage,
        Blueprint $blueprint,
        bool      $ignoreExistingStructure = false,
    ): ActionSet
    {
        $driverHandler = $this->pdoStorageController->getDatabaseController($storage)->driverHandler;

        if ($ignoreExistingStructure) {
            $existingStructure = null;
        }
        else {
            $existingStructure = $driverHandler->tableStructureFinder()->find($this->pdoStorageController->store(
                $blueprint->name,
                $storage
            ));
        }

        if ($existingStructure === null) {
            return $this->createTableBuilder->create($storage, $blueprint);
        }
        else {
            $changes = $this->changeFinder->find($blueprint, $existingStructure);

            return $changes
                ? $this->alterTableBuilder->create($storage, $blueprint, $changes)
                : new ActionSet();
        }
    }

    public function handles(Storage $storage): bool
    {
        return $storage instanceof Database;
    }
}
