<?php

declare(strict_types=1);

namespace Medas\PdoMysqlMigrationBuilder\Exceptions;

use Medas\Core\Exceptions\BaseException;

class InvalidForeignKeyAction extends BaseException
{
    public function __construct(string $actionString)
    {
        parent::__construct($actionString);
    }

    public function pattern(): string
    {
        return 'invalid on delete/update action string "%s"';
    }
}
