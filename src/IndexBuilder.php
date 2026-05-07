<?php

declare(strict_types=1);

namespace Medas\PdoMysqlMigrationBuilder;

use Medas\Core\Attributes\Service;
use Medas\MigrationBuilder\Structure\Blueprint\{Field, Index};
use Medas\PdoStorage\{Database, Drivers\DriverHandler};

#[Service]
readonly class IndexBuilder
{
    public function buildAdd(DriverHandler $driver, Database $database, Index $index): string
    {
        $definition = '';

        if ($index->isPrimary) {
            $definition .= ' primary key (';
        }
        else {
            if ($index->isUnique) {
                $definition .= ' unique';
            }

            $definition .= ' key '
                . $driver->quote($database, $this->createIndexName($index))
                . ' (';
        }

        foreach ($index->fields() as $field) {
            $definition .= $driver->quote($database, $field->name) . ',';
        }

        return substr($definition, 0, -1) . ')';
    }

    private function createIndexName(Index $index): string
    {
        $names = array_map(fn(Field $field) => $field->name, $index->fields());

        return sha1((implode("\n", $names)));
    }
}
