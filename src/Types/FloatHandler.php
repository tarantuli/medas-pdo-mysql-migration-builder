<?php

declare(strict_types=1);

namespace Medas\PdoMysqlMigrationBuilder\Types;

use Medas\Core\Attributes\Service;

#[Service]
readonly class FloatHandler
{
    public function handle(): string
    {
        return 'double';
    }
}
