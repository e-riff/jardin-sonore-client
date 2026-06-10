<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $validatorConfig = [
        'validation' => [],
    ];

    if ('test' === $containerConfigurator->env()) {
        $validatorConfig['validation']['not_compromised_password'] = false;
    }

    $containerConfigurator->extension('framework', $validatorConfig);
};
