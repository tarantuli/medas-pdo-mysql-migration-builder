<?php

declare(strict_types=1);

namespace Medas\PdoMysqlMigrationBuilder\TableStructureFinder;

use Medas\Core\Attributes\Service;
use Medas\PdoStorage\{
    Exceptions\PdoDatabase,
    PdoStorageController,
    Queries\Query,
    Queries\QueryExecutor,
    Table
};

#[Service]
readonly class TableStructureStringFinder
{
    public function __construct(
        private PdoStorageController $pdoStorageController,
        private QueryExecutor        $queryExecutor,
    )
    {
    }

    public function find(Table $table): string|null
    {
        if (!$this->pdoStorageController->hasStore($table)) {
            return null;
        }

        $quotedTable = $this->pdoStorageController->quote($table->database, $table->name);
        $query = new Query('show create table ' . $quotedTable, [], $table->database);

        try {
            $this->queryExecutor->execute($query);
        }
        catch (PdoDatabase) {
            return null;
        }

        $data = $query->recordSet();

        return $data->hasRecords() ? $data->fetchRecord()['Create Table'] : null;
    }
}
