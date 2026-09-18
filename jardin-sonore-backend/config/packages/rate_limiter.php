<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'framework' => [
        'rate_limiter' => [
            'portal_login' => [
                'policy' => 'fixed_window',
                'limit' => 5,
                'interval' => '1 minute',
            ],
            'portal_password_reset' => [
                'policy' => 'fixed_window',
                'limit' => 5,
                'interval' => '1 minute',
            ],
        ],
    ],
]);
