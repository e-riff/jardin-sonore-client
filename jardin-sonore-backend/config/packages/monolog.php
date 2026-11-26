<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'monolog' => [
        'channels' => [
            'deprecation',
            'mail',
            'messenger',
            'mailing_delivery',
            'session_document',
            'portal_security',
        ],
        'handlers' => [
            'mail' => ['type' => 'rotating_file', 'path' => '%kernel.logs_dir%/mail.log', 'level' => 'info', 'max_files' => 30, 'channels' => ['mail']],
            'messenger' => ['type' => 'rotating_file', 'path' => '%kernel.logs_dir%/messenger.log', 'level' => 'info', 'max_files' => 30, 'channels' => ['messenger']],
        ],
    ],
    'when@dev' => [
        'monolog' => [
            'handlers' => [
                'main' => [
                    'type' => 'rotating_file',
                    'max_files' => 30,
                    'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                    'level' => 'debug',
                    'channels' => ['!event', '!mail', '!messenger', '!mailing_delivery', '!session_document', '!portal_security'],
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
                'session_document' => ['type' => 'rotating_file', 'path' => '%kernel.logs_dir%/session_document.log', 'level' => 'info', 'max_files' => 30, 'channels' => ['session_document']],
                'portal_security' => ['type' => 'rotating_file', 'path' => '%kernel.logs_dir%/portal_security.log', 'level' => 'info', 'max_files' => 30, 'channels' => ['portal_security']],
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
                    'channels' => ['!event', '!mail', '!messenger', '!mailing_delivery', '!session_document', '!portal_security'],
                ],
                'nested' => [
                    'type' => 'rotating_file',
                    'max_files' => 30,
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
                'session_document' => ['type' => 'rotating_file', 'path' => '%kernel.logs_dir%/session_document.log', 'level' => 'info', 'max_files' => 30, 'channels' => ['session_document']],
                'portal_security' => ['type' => 'rotating_file', 'path' => '%kernel.logs_dir%/portal_security.log', 'level' => 'info', 'max_files' => 30, 'channels' => ['portal_security']],
            ],
        ],
    ],
    'when@prod' => [
        'monolog' => [
            'handlers' => [
                'main' => [
                    'type' => 'fingers_crossed',
                    'action_level' => 'warning',
                    'handler' => 'nested',
                    'excluded_http_codes' => [404, 405],
                    'channels' => ['!deprecation', '!mail', '!messenger', '!mailing_delivery', '!session_document', '!portal_security'],
                    'buffer_size' => 50,
                ],
                'nested' => [
                    'type' => 'rotating_file',
                    'max_files' => 30,
                    'path' => '%kernel.logs_dir%/%kernel.environment%.log',
                    'level' => 'info',
                ],
                'console' => [
                    'type' => 'console',
                    'process_psr_3_messages' => false,
                    'channels' => ['!event', '!doctrine'],
                ],
                'deprecation' => [
                    'type' => 'rotating_file',
                    'max_files' => 30,
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
                'session_document' => ['type' => 'rotating_file', 'path' => '%kernel.logs_dir%/session_document.log', 'level' => 'info', 'max_files' => 30, 'channels' => ['session_document']],
                'portal_security' => ['type' => 'rotating_file', 'path' => '%kernel.logs_dir%/portal_security.log', 'level' => 'info', 'max_files' => 30, 'channels' => ['portal_security']],
            ],
        ],
    ],
]);
