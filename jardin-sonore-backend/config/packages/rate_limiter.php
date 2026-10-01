<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'framework' => [
        'rate_limiter' => [
            'newsletter_subscription_ip' => ['policy' => 'fixed_window', 'limit' => 5, 'interval' => '1 minute'],
            'newsletter_subscription_address' => ['policy' => 'fixed_window', 'limit' => 3, 'interval' => '1 hour'],
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
