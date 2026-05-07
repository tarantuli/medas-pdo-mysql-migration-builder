<?php

declare(strict_types=1);

namespace Medas\PdoMysqlMigrationBuilder\TableBuilders;

use Medas\PdoStorage\{Database, Drivers\DriverHandler, Queries\QuerySet};
use Medas\MigrationBuilder\Structure\{Blueprint, Blueprint\Field, Changes\Changes};

class Job
{
    public Changes $changes;
    public string|null $baseQuery = null;

    /** @var Field[] */
    public array $collections = [];

    public array $foreignKeys = [];
    public QuerySet $querySet;

    public function __construct(
        public Database      $database,
        public DriverHandler $driverHandler,
        public Blueprint     $blueprint,
    )
    {
        $this->querySet = new QuerySet();
    }
}
