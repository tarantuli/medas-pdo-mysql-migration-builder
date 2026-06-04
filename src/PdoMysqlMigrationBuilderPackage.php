<?php

declare(strict_types=1);

namespace Medas\PdoMysqlMigrationBuilder;

use Medas\Core\{AsSingleton, BasePackage};
use Medas\MigrationBuilder\MigrationBuilderPackage;

class PdoMysqlMigrationBuilderPackage extends BasePackage
{
    use AsSingleton;

    public function dependencies(): array
    {
        return [
            MigrationBuilderPackage::instance(),
        ];
    }

    public function sourceDirectory(): string
    {
        return __DIR__;
    }
}
