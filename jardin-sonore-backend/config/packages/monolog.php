<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'monolog' => [
        'channels' => [
            'deprecation',
            'mailing_delivery',
        ],
    ],
    'when@dev' => [
        'monolog' => [
            'handlers' => [
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
            ],
        ],
    ],
    'when@test' => [
        'monolog' => [
            'handlers' => [
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
            ],
        ],
    ],
    'when@prod' => [
        'monolog' => [
            'handlers' => [
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
                    'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                    'level' => 'info',
                ],
                'console' => [
                    'type' => 'console',
                    'process_psr_3_messages' => false,
                    'channels' => ['!event', '!doctrine'],
                ],
                'deprecation' => [
                    'type' => 'stream',
                    'channels' => ['deprecation'],
                    'path' => '%kernel.logs_dir%/%kernel.environment%.deprecations.log',
                    'level' => 'notice',
                ],
                'mailing_delivery' => [
                    'type' => 'rotating_file',
                    'path' => '%kernel.logs_dir%/mailing_delivery.log',
                    'level' => 'info',
                    'max_files' => 30,
                    'channels' => ['mailing_delivery'],
                ],
            ],
        ],
    ],
]);
