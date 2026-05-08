<?php

declare(strict_types=1);

namespace Medas\PdoMysqlMigrationBuilder;

use Medas\Core\Attributes\Service;

#[Service]
readonly class CollectionProcessor
{
    public function __construct(
        private JoinTableManager $joinTableManager,
    )
    {
    }

    public function process(TableBuilders\Job $job): void
    {
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
