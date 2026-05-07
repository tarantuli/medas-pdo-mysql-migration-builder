<?php

declare(strict_types=1);

namespace Medas\PdoMysqlMigrationBuilder;

use Medas\Core\Attributes\Service;
use Medas\MigrationBuilder\Structure\{Blueprint, Changes\Changes};
use Medas\PdoStorage\{Database, PdoStorageController, Queries\Query, Queries\QuerySet};
use Medas\StorageManager\{Type, UnitOfWork\Priority};

#[Service]
readonly class AlterTableBuilder
{
    public function __construct(
        private FieldToDefinitionConverter  $fieldToDefinitionConverter,
        private ForeignKeyConstraintBuilder $foreignKeyConstraintBuilder,
        private IndexBuilder                $indexBuilder,
        private JoinTableManager            $joinTableManager,
        private PdoStorageController        $pdoStorageController,
    )
    {
    }

    public function create(Database $database, Blueprint $blueprint, Changes $changes): QuerySet
    {
        $job = new TableBuilders\Job(
            $database,
            $this->pdoStorageController->getDatabaseController($database)->driverHandler,
            $blueprint,
        );

        $job->changes = $changes;

        $this->processFields($job);
        $this->processIndexes($job);
        $this->processForeignKeys($job);

        if ($job->baseQuery !== null) {
            $job->querySet[] = new Query(
                substr($job->baseQuery, 0, -2),
                [],
                $job->database,
                Priority::AlterStore
            );
        }

        $this->processCollections($job);

        return $job->querySet;
    }

    private function processFields(TableBuilders\Job $job): void
    {
        foreach ($job->changes->addFields as $field) {
            if ($field->type === Type::Collection) {
                $job->collections[] = $field;

                continue;
            }

            $definition = $this->fieldToDefinitionConverter->buildDefinition(
                $job->database,
                $field
            );

            if ($definition !== null) {
                if ($job->baseQuery === null) {
                    $job->baseQuery = $this->startAlterQuery($job);
                }

                $job->baseQuery .= sprintf(
                    "add column %s %s,\n",
                    $job->driverHandler->quote($job->database, $field->name),
                    $definition,
                );
            }
        }

        foreach ($job->changes->changeFields as $field) {
            if ($field->type === Type::Collection) {
                $job->collections[] = $field;

                continue;
            }

            $definition = $this->fieldToDefinitionConverter->buildDefinition(
                $job->database,
                $field
            );

            if ($definition !== null) {
                if ($job->baseQuery === null) {
                    $job->baseQuery = $this->startAlterQuery($job);
                }

                $job->baseQuery .= sprintf(
                    "modify column %1\$s %2\$s,\n",
                    $job->driverHandler->quote($job->database, $field->name),
                    $definition,
                );
            }
        }
    }

    private function processIndexes(TableBuilders\Job $job): void
    {
        foreach ($job->changes->addIndexes as $index) {
            if ($job->baseQuery === null) {
                $job->baseQuery = $this->startAlterQuery($job);
            }

            $job->baseQuery .= 'add'
                . $this->indexBuilder->buildAdd($job->driverHandler, $job->database, $index)
                . ",\n";
        }
    }

    private function processForeignKeys(TableBuilders\Job $job): void
    {
        if (!$job->changes->changeForeignKey && !$job->changes->addForeignKey) {
            return;
        }

        if ($job->changes->changeForeignKey) {
            $query = $this->startAlterQuery($job);

            foreach ($job->changes->changeForeignKey as $foreignKey) {
                $query .= $this->foreignKeyConstraintBuilder
                    ->buildDrop($job->changes->name, $job->driverHandler, $job->database, $foreignKey)
                        . ",\n";
            }

            $job->querySet[] = new Query(
                substr($query, 0, -2),
                [],
                $job->database,
                Priority::DeleteStoreRelations
            );
        }

        $query = $this->startAlterQuery($job);
        $foreignKeys = array_merge($job->changes->changeForeignKey, $job->changes->addForeignKey);

        foreach ($foreignKeys as $foreignKey) {
            $query .= $this->foreignKeyConstraintBuilder
                ->buildAdd($job->changes->name, $job->driverHandler, $job->database, $foreignKey)
                    . ",\n";
        }

        $job->querySet[] = new Query(
            substr($query, 0, -2),
            [],
            $job->database,
            Priority::AddStoreRelations
        );
    }

    private function startAlterQuery(TableBuilders\Job $job): string
    {
        return 'alter table '
            . $job->driverHandler->quote($job->database, $job->changes->name)
            . "\n";
    }

    private function processCollections(TableBuilders\Job $job): void
    {
        if (!$job->collections) {
            return;
        }

        foreach ($job->collections as $collectionField) {
            $queries = $this->joinTableManager->createQueries(
                $job->database,
                $job->blueprint,
                $collectionField
            );

            if ($queries) {
                foreach ($queries as $query) {
                    $job->querySet[] = $query;
                }
            }
        }
    }
}
