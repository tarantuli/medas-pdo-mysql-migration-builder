<?php

declare(strict_types=1);

namespace Medas\PdoMysqlMigrationBuilder\Types;

use Medas\Core\{Attributes\Service, Types\Integer};
use Medas\MigrationBuilder\Structure\Blueprint\Field;

#[Service]
readonly class IntegerHandler
{
    public function handle(Field $field): string
    {
        return match (true) {
            $field->minValue >= 0 && $field->maxValue <= Integer::UNSIGNED_1_BYTE_MAX => 'tinyint unsigned',
            $field->minValue >= 0 && $field->maxValue <= Integer::UNSIGNED_2_BYTE_MAX => 'smallint unsigned',
            $field->minValue >= 0 && $field->maxValue <= Integer::UNSIGNED_3_BYTE_MAX => 'mediumint unsigned',
            $field->minValue >= 0 && $field->maxValue <= Integer::UNSIGNED_4_BYTE_MAX => 'int unsigned',
            $field->minValue >= 0 && $field->maxValue <= Integer::UNSIGNED_8_BYTE_MAX => 'bigint unsigned',

            $field->minValue >= -Integer::SIGNED_1_BYTE_MAX && $field->maxValue <= Integer::SIGNED_1_BYTE_MAX
                => 'tinyint',

            $field->minValue >= -Integer::SIGNED_2_BYTE_MAX && $field->maxValue <= Integer::SIGNED_2_BYTE_MAX
                => 'mediumint',

            $field->minValue >= -Integer::SIGNED_3_BYTE_MAX && $field->maxValue <= Integer::SIGNED_3_BYTE_MAX => 'int',

            $field->minValue >= -Integer::SIGNED_4_BYTE_MAX && $field->maxValue <= Integer::SIGNED_4_BYTE_MAX
                => 'bigint',

            default => throw new \Exception('out of bounds value range'),
        };
    }
}
