<?php

declare(strict_types=1);

namespace Medas\PdoMysqlMigrationBuilder;

use Medas\Core\Attributes\Service;
use Medas\EntityManager\Attributes\Relations\Action;
use Medas\MigrationBuilder\{
    OriginalClassStorageStrategyActionBuilder\ForLinkingStore,
    Structure\Blueprint
};
use Medas\PdoStorage\{Database, PdoStorageController, Queries\Query, Queries\QuerySet};
use Medas\StorageManager\{Type, UnitOfWork\Priority};

#[Service]
readonly class CreateTableBuilder
{
    public function __construct(
        private FieldToDefinitionConverter  $fieldToDefinitionConverter,
        private ForLinkingStore             $forLinkingStore,
        private ForeignKeyConstraintBuilder $foreignKeyConstraintBuilder,
        private IndexBuilder                $indexBuilder,
        private JoinTableManager            $joinTableManager,
        private PdoStorageController        $pdoStorageController,
    )
    {
    }

    public function create(Database $database, Blueprint $blueprint): QuerySet
    {
        $job = new TableBuilders\Job(
            $database,
            $this->pdoStorageController->getDatabaseController($database)->driverHandler,
            $blueprint,
        );

        $tableName = $job->driverHandler->quote($database, $job->blueprint->name);
        $job->baseQuery = sprintf(/** @lang text */ "create table %s (\n", $tableName);

        $this->addFields($job);
        $this->addKeys($job);
        $this->processForeignKeys($job);
        $this->handleOriginalEntityType($job);

        $job->baseQuery = substr($job->baseQuery, 0, -2);
        $job->baseQuery .= "\n)\n";
        $job->querySet[] = new Query($job->baseQuery, [], $job->database, Priority::CreateStore);

        if ($job->foreignKeys) {
            $query = sprintf("alter table %s\n%s", $tableName, implode(",\n", $job->foreignKeys));
            $job->querySet[] = new Query($query, [], $job->database, Priority::AddStoreRelations);
        }

        $this->processCollections($job);

        return $job->querySet;
    }

    protected function addFields(TableBuilders\Job $job): void
    {
        foreach ($job->blueprint->fields as $field) {
            if ($field->store !== null && $field->store !== $job->blueprint->name) {
                // If this is the primary key, add it without generating value
                $primaryIndex = $job->blueprint->primaryIndex();

                if ($primaryIndex && in_array($field, $primaryIndex->fields())) {
                    $foreignKey = new Blueprint\ForeignKey(
                        $field->name,
                        $field->store,
                        $field->name,
                        Action::Cascade,
                        Action::Cascade
                    );

                    $job->blueprint->addForeignKey($foreignKey);

                    $field->isGenerated = false;
                }
                else {
                    continue;
                }
            }

            if ($field->type === Type::Collection) {
                $job->collections[] = $field;

                continue;
            }

            $definition = $this->fieldToDefinitionConverter->buildDefinition(
                $job->database,
                $field
            );

            if ($definition !== null) {
                $job->baseQuery .= sprintf(
                    " %s %s,\n",
                    $job->driverHandler->quote($job->database, $field->name),
                    $definition,
                );
            }
        }
    }

    protected function addKeys(TableBuilders\Job $job): void
    {
        foreach ($job->blueprint->indexes as $index) {
            foreach ($index->fields() as $field) {
                if ($field->store !== null && $field->store !== $job->blueprint->name) {
                    // If this is the primary key, do add it
                    $primaryIndex = $job->blueprint->primaryIndex();

                    if ($primaryIndex && in_array($field, $job->blueprint->primaryIndex()->fields())) {
                        // Do nothing
                    }
                    else {
                        continue 2;
                    }
                }
            }

            $job->baseQuery .= $this->indexBuilder->buildAdd($job->driverHandler, $job->database, $index)
                . ",\n";
        }
    }

    protected function processForeignKeys(TableBuilders\Job $job): void
    {
        foreach ($job->blueprint->foreignKeys as $foreignKey) {
            $job->foreignKeys[] = $this->foreignKeyConstraintBuilder
                ->buildAdd($job->blueprint->name, $job->driverHandler, $job->database, $foreignKey);
        }
    }

    private function handleOriginalEntityType(TableBuilders\Job $job): void
    {
        if (!$job->blueprint->storeOriginalClass) {
            return;
        }

        if ($job->blueprint->storeRequestingOriginalClassStorage !== $job->blueprint->name) {
            return;
        }

        foreach ($this->forLinkingStore->buildStoreActions($job->blueprint, $job->database) as $query) {
            $job->querySet[] = $query;
        }
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
