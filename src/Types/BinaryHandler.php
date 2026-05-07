<?php

declare(strict_types=1);

namespace Medas\PdoMysqlMigrationBuilder\Types;

use Medas\Core\{Attributes\Service, Types\Integer};
use Medas\MigrationBuilder\Structure\Blueprint\Field;

#[Service]
readonly class BinaryHandler
{
    public function handle(Field $field): string
    {
        /** @noinspection PhpDuplicateMatchArmBodyInspection */
        return match (true) {
            $field->maxLength <= Integer::UNSIGNED_1_BYTE_MAX => $field->minLength === $field->maxLength
                ? sprintf('binary(%u)', $field->maxLength)
                : sprintf('varbinary(%u)', $field->maxLength),

            $field->maxLength <= Integer::UNSIGNED_2_BYTE_MAX => 'blob',
            $field->maxLength <= Integer::UNSIGNED_3_BYTE_MAX => 'mediumblob',
            $field->maxLength <= Integer::UNSIGNED_4_BYTE_MAX => 'longblob',
            default => 'blob'
        };
    }
}
