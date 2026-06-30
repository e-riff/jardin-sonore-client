<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $monologConfig = [
        'channels' => [
            'deprecation',
            'mailing_delivery',
        ],
    ];

    if ('dev' === $containerConfigurator->env()) {
        $monologConfig['handlers'] = [
            'main' => [
                'type' => 'stream',
                'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                'level' => 'debug',
                'channels' => ['!event'],
            ],
            'console' => [
                'type' => 'console',
                'process_psr_3_messages' => false,
                'channels' => ['!event', '!doctrine', '!console'],
            ],
            'mailing_delivery' => [
                'type' => 'rotating_file',
                'path' => '%kernel.logs_dir%/mailing_delivery.log',
                'level' => 'info',
                'max_files' => 30,
                'channels' => ['mailing_delivery'],
            ],
        ];
    }

    if ('test' === $containerConfigurator->env()) {
        $monologConfig['handlers'] = [
            'main' => [
                'type' => 'fingers_crossed',
                'action_level' => 'error',
                'handler' => 'nested',
                'excluded_http_codes' => [404, 405],
                'channels' => ['!event'],
            ],
            'nested' => [
                'type' => 'stream',
                'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                'level' => 'debug',
            ],
            'mailing_delivery' => [
                'type' => 'rotating_file',
                'path' => '%kernel.logs_dir%/mailing_delivery.log',
                'level' => 'info',
                'max_files' => 30,
                'channels' => ['mailing_delivery'],
            ],
        ];
    }

    if ('prod' === $containerConfigurator->env()) {
        $monologConfig['handlers'] = [
            'main' => [
                'type' => 'fingers_crossed',
                'action_level' => 'error',
                'handler' => 'nested',
                'excluded_http_codes' => [404, 405],
                'channels' => ['!deprecation'],
                'buffer_size' => 50,
            ],
            'nested' => [
                'type' => 'stream',
                'path' => 'php://stderr',
                'level' => 'debug',
                'formatter' => 'monolog.formatter.json',
            ],
            'console' => [
                'type' => 'console',
                'process_psr_3_messages' => false,
                'channels' => ['!event', '!doctrine'],
            ],
            'deprecation' => [
                'type' => 'stream',
                'channels' => ['deprecation'],
                'path' => 'php://stderr',
                'formatter' => 'monolog.formatter.json',
            ],
            'mailing_delivery' => [
                'type' => 'rotating_file',
                'path' => '%kernel.logs_dir%/mailing_delivery.log',
                'level' => 'info',
                'max_files' => 30,
                'channels' => ['mailing_delivery'],
            ],
        ];
    }

    $containerConfigurator->extension('monolog', $monologConfig);
};
