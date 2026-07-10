<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'web_profiler' => [
        'toolbar' => [
            'enabled' => true,
            'ajax_replace' => true,
        ],
        'intercept_redirects' => false,
    ],
]);
