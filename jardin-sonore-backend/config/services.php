<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use App\Application\Portal\PortalAccountMailSenderInterface;
use App\Application\Session\SessionDocumentGeneratorInterface;
use App\Infrastructure\Mailer\SymfonyPortalAccountMailSender;
use App\Infrastructure\Session\DompdfSessionDocumentGenerator;
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
        SessionDocumentGeneratorInterface::class => [
            'alias' => DompdfSessionDocumentGenerator::class,
        ],
        PortalAccountMailSenderInterface::class => [
            'alias' => SymfonyPortalAccountMailSender::class,
        ],
    ],
]);
