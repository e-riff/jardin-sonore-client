<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'framework' => [
        'secret' => '%env(APP_SECRET)%',
        'session' => [
            'cookie_lifetime' => 604800,
            'gc_maxlifetime' => 604800,
        ],
    ],
    'when@test' => [
        'framework' => [
            'test' => true,
            'session' => [
                'storage_factory_id' => 'session.storage.factory.mock_file',
            ],
        ],
    ],
    'when@dev' => [
        'framework' => [
            'profiler' => [
                'collect' => true,
                'only_exceptions' => false,
                'only_main_requests' => false,
            ],
        ],
    ],
]);
