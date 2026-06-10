<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $frameworkConfig = [
        'secret' => '%env(APP_SECRET)%',
        'session' => true,
    ];

    if ('test' === $containerConfigurator->env()) {
        $frameworkConfig['test'] = true;
        $frameworkConfig['session'] = [
            'storage_factory_id' => 'session.storage.factory.mock_file',
        ];
    }

    $containerConfigurator->extension('framework', $frameworkConfig);
};
