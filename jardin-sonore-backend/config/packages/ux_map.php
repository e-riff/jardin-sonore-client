<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'ux_map' => [
        'renderer' => '%env(resolve:default::UX_MAP_DSN)%',
    ],
]);
