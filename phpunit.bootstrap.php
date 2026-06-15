<?php

declare(strict_types=1);

use Medas\ObjectInstantiator\ObjectInstantiator;
use Medas\PdoMysqlMigrationBuilder\PdoMysqlMigrationBuilderPackage;
use Medas\ServiceManager\{ServiceConfigBuilder, ServiceManager};

chdir(__DIR__);

new ServiceManager(function (): ServiceConfigBuilder {
    $config = new ServiceConfigBuilder(ObjectInstantiator::class);

    $config->addPackages([
        PdoMysqlMigrationBuilderPackage::instance(),
    ]);

    return $config;
});
