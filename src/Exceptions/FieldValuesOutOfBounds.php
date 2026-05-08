<?php

declare(strict_types=1);

namespace Medas\PdoMysqlMigrationBuilder\Exceptions;

use Medas\Core\Exceptions\BaseException;
use Medas\MigrationBuilder\Structure\Blueprint\Field;

class FieldValuesOutOfBounds extends BaseException
{
    public function __construct(Field $field)
    {
        parent::__construct($field->minValue, $field->maxValue);
    }

    public function pattern(): string
    {
        return 'field values out of bounds (min %s, max %s)';
    }
}
