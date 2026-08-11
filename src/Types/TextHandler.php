<?php

declare(strict_types=1);

namespace Medas\PdoMysqlMigrationBuilder\Types;

use Medas\Core\{Attributes\Service, Types\Integer};
use Medas\MigrationBuilder\Structure\Blueprint\Field;

#[Service]
readonly class TextHandler
{
    public function handle(Field $field): string
    {
        /** @noinspection PhpDuplicateMatchArmBodyInspection */
        return match (true) {
            $field->maxLength <= Integer::UNSIGNED_1_BYTE_MAX
                => $field->minLength === $field->maxLength
                    ? sprintf('char(%u)', $field->maxLength)
                    : sprintf('varchar(%u)', $field->maxLength),

            $field->maxLength <= Integer::UNSIGNED_2_BYTE_MAX => 'text',
            $field->maxLength <= Integer::UNSIGNED_3_BYTE_MAX => 'mediumtext',
            $field->maxLength <= Integer::UNSIGNED_4_BYTE_MAX => 'longtext',
            default => 'text'
        };
    }
}
