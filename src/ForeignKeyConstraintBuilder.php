<?php

declare(strict_types=1);

namespace Medas\PdoMysqlMigrationBuilder;

use Medas\Core\Attributes\Service;
use Medas\MigrationBuilder\Structure\Blueprint\ForeignKey;
use Medas\PdoStorage\{Database, Drivers\DriverHandler};

#[Service]
readonly class ForeignKeyConstraintBuilder
{
    public function buildAdd(
        string        $entityName,
        DriverHandler $driver,
        Database      $database,
        ForeignKey    $foreignKey
    ): string
    {
        return sprintf(
            " add constraint %s\n  foreign key (%s)\n  references %s (%s) %s %s",
            $driver->quote($database, $this->createForeignKeyName($entityName, $foreignKey)),
            $driver->quote($database, $foreignKey->field),
            $driver->quote($database, $foreignKey->foreignEntity),
            $driver->quote($database, $foreignKey->foreignField),
            'on delete ' . $foreignKey->onDelete->value,
            'on update ' . $foreignKey->onUpdate->value,
        );
    }

    public function buildDrop(
        string        $entityName,
        DriverHandler $driver,
        Database      $database,
        ForeignKey    $foreignKey
    ): string
    {
        return ' drop constraint '
            . $driver->quote($database, $this->createForeignKeyName($entityName, $foreignKey));
    }

    private function createForeignKeyName(string $entityName, ForeignKey $foreignKey): string
    {
        return sha1($entityName . "\n" . $foreignKey->field . "\n" . $foreignKey->foreignEntity . "\n" . $foreignKey->foreignField);
    }
}
