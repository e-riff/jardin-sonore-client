<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use App\Application\Commercial\CommercialContactMailSenderInterface;
use App\Application\Commercial\CommercialDigestSenderInterface;
use App\Application\Mailing\NewsletterConfirmationMailSenderInterface;
use App\Application\Mailing\NewsletterRecipientEligibilityInterface;
use App\Application\Portal\PortalAccountMailSenderInterface;
use App\Application\Session\SessionDocumentGeneratorInterface;
use App\Application\Session\SessionNotificationMailSenderInterface;
use App\Infrastructure\Mailer\SymfonyCommercialContactSender;
use App\Infrastructure\Mailer\SymfonyCommercialDigestSender;
use App\Infrastructure\Mailer\SymfonyNewsletterConfirmationMailSender;
use App\Infrastructure\Mailer\SymfonyPortalAccountMailSender;
use App\Infrastructure\Mailer\SymfonySessionNotificationMailSender;
use App\Infrastructure\Mailing\DoctrineNewsletterRecipientEligibility;
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
        SessionNotificationMailSenderInterface::class => [
            'alias' => SymfonySessionNotificationMailSender::class,
        ],
        CommercialContactMailSenderInterface::class => [
            'alias' => SymfonyCommercialContactSender::class,
        ],
        CommercialDigestSenderInterface::class => [
            'alias' => SymfonyCommercialDigestSender::class,
        ],
        NewsletterConfirmationMailSenderInterface::class => [
            'alias' => SymfonyNewsletterConfirmationMailSender::class,
        ],
        NewsletterRecipientEligibilityInterface::class => [
            'alias' => DoctrineNewsletterRecipientEligibility::class,
        ],
    ],
]);
