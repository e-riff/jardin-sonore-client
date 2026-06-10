<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

return static function (ContainerConfigurator $containerConfigurator): void {
    $securityConfig = [
        'password_hashers' => [
            PasswordAuthenticatedUserInterface::class => 'auto',
        ],
        'providers' => [
            'users_in_memory' => [
                'memory' => null,
            ],
        ],
        'firewalls' => [
            'dev' => [
                'pattern' => '^/(_profiler|_wdt|assets|build)/',
                'security' => false,
            ],
            'main' => [
                'lazy' => true,
                'provider' => 'users_in_memory',
            ],
        ],
        'access_control' => [],
    ];

    if ('test' === $containerConfigurator->env()) {
        $securityConfig['password_hashers'][PasswordAuthenticatedUserInterface::class] = [
            'algorithm' => 'auto',
            'cost' => 4,
            'time_cost' => 3,
            'memory_cost' => 10,
        ];
    }

    $containerConfigurator->extension('security', $securityConfig);
};
