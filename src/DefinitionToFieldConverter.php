<?php

declare(strict_types=1);

namespace Medas\PdoMysqlMigrationBuilder;

use Medas\Core\{Attributes\Service, CaseInsensitiveString, Types\Integer};
use Medas\PdoStorage\Exceptions\{CantDetermineTypeFromDefinition, CantTurnDefinitionIntoVariable};
use Medas\MigrationBuilder\Structure\Blueprint\{Field};
use Medas\StorageManager\Type;

#[Service]
readonly class DefinitionToFieldConverter
{
    const string CREATION_TIMESTAMP_DEFINITION = ' default current_timestamp()';
    const string MODIFICATION_TIMESTAMP_DEFINITION = ' default current_timestamp() on update current_timestamp()';

    public function convert(string $name, string $definition): Field
    {
        $remainder = new CaseInsensitiveString($definition);
        $isNullable = true;
        $isGenerated = false;
        $hasDefault = false;
        $default = null;
        $isCreationTimestamp = false;
        $isModificationTimestamp = false;

        // Strip collation
        $remainder = $remainder->regexReplace('/ collate \w+/i', '');

        // Strip comment
        $remainder = $remainder->regexReplace('/ comment \'.+?\'/i', '');

        if ($remainder->endsWith(' auto_increment')) {
            $remainder = $remainder->chopFromEnd(' auto_increment');
            $isNullable = false;
            $isGenerated = true;
        }

        if ($remainder->endsWith(self::CREATION_TIMESTAMP_DEFINITION)) {
            $remainder = $remainder->chopFromEnd(self::CREATION_TIMESTAMP_DEFINITION);
            $isCreationTimestamp = true;
        }

        if ($remainder->endsWith(self::MODIFICATION_TIMESTAMP_DEFINITION)) {
            $remainder = $remainder->chopFromEnd(self::MODIFICATION_TIMESTAMP_DEFINITION);
            $isModificationTimestamp = true;
        }

        if ($match = $remainder->regexMatch('/ default (.+)$/i')) {
            $hasDefault = true;
            $remainder = $remainder->chopFromEnd($match[0]);
            $default = $this->parseString($match[1]);

            if ($default === null) {
                $hasDefault = false;
            }
        }

        if ($remainder->endsWith(' not null')) {
            $remainder = $remainder->chopFromEnd(' not null');
            $isNullable = false;
        }

        $intMatch = $remainder->regexMatch('/((?:tiny|small|medium|big)?int)(?:\(\d+\))?( unsigned)?/i');
        $type = match (true) {
            $intMatch !== null => Type::Integer,

            $remainder->startsWith('varchar('), $remainder->startsWith('char('), $remainder->startsWith('text'), $remainder->endsWith('text')
                => Type::Text,

            $remainder->startsWith('varbinary('), $remainder->startsWith('binary('), $remainder->startsWith('blob'), $remainder->endsWith('blob')
                => Type::Binary,

            $remainder->equals('datetime') => Type::DateTime,
            $remainder->equals('date') => Type::Date,
            $remainder->equals('float') => Type::Float,
            $remainder->equals('double') => Type::Float,
            $remainder->startsWith('year(') => Type::Integer,
            default => throw new CantDetermineTypeFromDefinition((string) $remainder, $definition),
        };

        $minValue = 0;
        $maxValue = Integer::UNSIGNED_4_BYTE_MAX;

        if ($intMatch !== null) {
            if (isset($intMatch[2])) {
                $maxValue = match ($intMatch[1]) {
                    'tinyint' => Integer::UNSIGNED_1_BYTE_MAX,
                    'smallint' => Integer::UNSIGNED_2_BYTE_MAX,
                    'mediumint' => Integer::UNSIGNED_3_BYTE_MAX,
                    'int' => Integer::UNSIGNED_4_BYTE_MAX,
                    default => Integer::UNSIGNED_8_BYTE_MAX,
                };
            }
            else {
                $minValue = match ($intMatch[1]) {
                    'tinyint' => Integer::SIGNED_1_BYTE_MIN,
                    'smallint' => Integer::SIGNED_2_BYTE_MIN,
                    'mediumint' => Integer::SIGNED_3_BYTE_MIN,
                    'int' => Integer::SIGNED_4_BYTE_MIN,
                    default => Integer::SIGNED_8_BYTE_MIN,
                };

                $maxValue = match ($intMatch[1]) {
                    'tinyint' => Integer::SIGNED_1_BYTE_MAX,
                    'smallint' => Integer::SIGNED_2_BYTE_MAX,
                    'mediumint' => Integer::SIGNED_3_BYTE_MAX,
                    'int' => Integer::SIGNED_4_BYTE_MAX,
                    default => Integer::SIGNED_8_BYTE_MAX,
                };
            }
        }

        $minLength = 0;
        $maxLength = match (true) {
            $remainder->equals('tinytext') || $remainder->equals('tinyblob') => Integer::UNSIGNED_1_BYTE_MAX,
            $remainder->equals('text') || $remainder->equals('blob') => Integer::UNSIGNED_2_BYTE_MAX,
            $remainder->equals('mediumtext') || $remainder->equals('mediumblob') => Integer::UNSIGNED_3_BYTE_MAX,
            $remainder->equals('longtext') || $remainder->equals('longblob') => Integer::UNSIGNED_4_BYTE_MAX,
            default => Integer::UNSIGNED_1_BYTE_MAX,
        };

        return new Field(
            name: $name,
            type: $type,
            isNullable: $isNullable,
            isGenerated: $isGenerated,
            isCreationTimestamp: $isCreationTimestamp,
            isModificationTimestamp: $isModificationTimestamp,
            hasDefault: $hasDefault,
            default: $default,
            minValue: $minValue,
            maxValue: $maxValue,
            minLength: $minLength,
            maxLength: $maxLength,
        );
    }

    private function parseString(string $string): string|int|null
    {
        $definition = new CaseInsensitiveString($string);

        if ($definition->equals('null')) {
            return null;
        }

        if ($definition->regexMatch('/^-?\d+$/')) {
            return (int) $string;
        }

        if ($definition->surroundedBy("'")) {
            return substr($string, 1, -1);
        }

        throw new CantTurnDefinitionIntoVariable($string);
    }
}
