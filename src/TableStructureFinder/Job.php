<?php

declare(strict_types=1);

namespace Medas\PdoMysqlMigrationBuilder\TableStructureFinder;

use Medas\MigrationBuilder\Structure\Blueprint;
use Medas\PdoStorage\{Database, Table};

class Job
{
    public Blueprint $blueprint;
    public string|null $createTable;

    public function __construct(
        public readonly Database $database,
        public readonly Table    $table,
    )
    {
        $this->blueprint = new Blueprint();
    }
}
