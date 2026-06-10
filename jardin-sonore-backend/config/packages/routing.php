<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $routerConfig = [
        'default_uri' => '%env(DEFAULT_URI)%',
    ];

    if ('prod' === $containerConfigurator->env()) {
        $routerConfig['strict_requirements'] = null;
    }

    $containerConfigurator->extension('framework', [
        'router' => $routerConfig,
    ]);
};
