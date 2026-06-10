<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $twigConfig = [
        'file_name_pattern' => '*.twig',
    ];

    if ('test' === $containerConfigurator->env()) {
        $twigConfig['strict_variables'] = true;
    }

    $containerConfigurator->extension('twig', $twigConfig);
};
