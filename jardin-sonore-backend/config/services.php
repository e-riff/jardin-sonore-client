<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Gedmo\Sluggable\SluggableListener;
use Gedmo\Timestampable\TimestampableListener;

return App::config([
    'imports' => [
        ['resource' => 'parameters.yaml.dist', 'type' => 'yaml'],
        ['resource' => 'parameters.yaml', 'type' => 'yaml', 'ignore_errors' => 'not_found'],
    ],
    'services' => [
        'App\\' => [
            'resource' => '../src/',
            'exclude' => [
                '../src/Infrastructure/Doctrine/Mapping',
                '../src/Kernel.php',
            ],
        ],
        TimestampableListener::class => [
            'tags' => [
                ['doctrine.event_subscriber' => ['connection' => 'default']],
            ],
        ],
        SluggableListener::class => [
            'tags' => [
                ['doctrine.event_subscriber' => ['connection' => 'default']],
            ],
        ],
    ],
]);
