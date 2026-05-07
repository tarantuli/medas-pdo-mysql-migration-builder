<?php

declare(strict_types=1);

namespace Medas\PdoMysqlMigrationBuilder\Types;

use Medas\Core\Attributes\Service;

#[Service]
readonly class BooleanHandler
{
    public function handle(): string
    {
        return 'tinyint unsigned';
    }
}
