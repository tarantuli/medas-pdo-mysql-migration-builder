<?php

declare(strict_types=1);

namespace Medas\PdoMysqlMigrationBuilder;

use Medas\Core\Attributes\Service;
use Medas\PdoStorage\Database;
use Medas\PdoStorage\Drivers\Interfaces\FieldHandler;
use Medas\PdoStorage\PdoStorageController;
use Medas\MigrationBuilder\Structure\Blueprint\Field;

#[Service]
readonly class FieldToDefinitionConverter implements FieldHandler
{
    public function __construct(
        private PdoStorageController $pdoStorageController,
    )
    {
    }

    public function buildDefinition(Database $database, Field $field): string|null
    {
        $driverHandler = $this->pdoStorageController->getDatabaseController($database)->driverHandler;

        if ($field->isCreationTimestamp || $field->isModificationTimestamp) {
            $default = ' default current_timestamp()';

            if ($field->isModificationTimestamp) {
                $default .= ' on update current_timestamp()';
            }
        }
        elseif ($field->hasDefault) {
            $serializedDefault = $driverHandler->serializer()->serialize($field->default);
            $default = ' default ' . $driverHandler->escape($database, $serializedDefault);
        }
        else {
            $default = '';
        }

        $baseDefinition = $driverHandler->typeHandler()->getBaseDefinition($database, $field);

        if ($baseDefinition === null) {
            return null;
        }

        return $baseDefinition
            . ($field->isNullable ? '' : ' not null')
            . ($field->isGenerated ? ' auto_increment' : '')
            . $default;
    }
}
