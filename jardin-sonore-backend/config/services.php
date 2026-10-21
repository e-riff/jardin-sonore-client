<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use App\Application\Commercial\CommercialCalendarGateway;
use App\Application\Commercial\CommercialContactMailSenderInterface;
use App\Application\Commercial\CommercialDigestSenderInterface;
use App\Application\Commercial\GoogleCalendarConnectionStoreInterface;
use App\Application\Mailing\NewsletterConfirmationMailSenderInterface;
use App\Application\Mailing\NewsletterRecipientEligibilityInterface;
use App\Application\Mcp\CreateStandaloneMailDraft;
use App\Application\Mcp\EditStandaloneMailDraft;
use App\Application\Mcp\ImapClientProvider;
use App\Application\Mcp\MailDraftEditor;
use App\Application\Mcp\MailDraftReceiptStore;
use App\Application\Mcp\MailDraftStore;
use App\Application\Mcp\MailDraftWriter;
use App\Application\Mcp\MailReader;
use App\Application\Mcp\ProjectContextReader;
use App\Application\Mcp\ProjectFollowupWriter;
use App\Application\Mcp\ProjectScheduleManager;
use App\Application\Mcp\ProjectSearch;
use App\Application\Mcp\SaveMailDraft;
use App\Application\Mcp\StandaloneMailDraftEditor;
use App\Application\Mcp\StandaloneMailDraftWriter;
use App\Application\Mcp\UpdateMailDraft;
use App\Application\Portal\PortalAccountMailSenderInterface;
use App\Application\Session\SessionDocumentGeneratorInterface;
use App\Application\Session\SessionNotificationMailSenderInterface;
use App\Infrastructure\Doctrine\Commercial\DoctrineGoogleCalendarConnectionStore;
use App\Infrastructure\Doctrine\Commercial\DoctrineProjectContextReader;
use App\Infrastructure\Doctrine\Commercial\DoctrineProjectFollowupWriter;
use App\Infrastructure\Doctrine\Commercial\DoctrineProjectScheduleManager;
use App\Infrastructure\Doctrine\Commercial\DoctrineProjectSearch;
use App\Infrastructure\Doctrine\Mcp\DoctrineMailDraftReceiptStore;
use App\Infrastructure\Google\GoogleCalendarGateway;
use App\Infrastructure\Mail\ImapMailDraftStore;
use App\Infrastructure\Mail\ImapMailReader;
use App\Infrastructure\Mail\WebklexImapClientProvider;
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
        GoogleCalendarConnectionStoreInterface::class => [
            'alias' => DoctrineGoogleCalendarConnectionStore::class,
        ],
        CommercialCalendarGateway::class => [
            'alias' => GoogleCalendarGateway::class,
        ],
        NewsletterConfirmationMailSenderInterface::class => [
            'alias' => SymfonyNewsletterConfirmationMailSender::class,
        ],
        NewsletterRecipientEligibilityInterface::class => [
            'alias' => DoctrineNewsletterRecipientEligibility::class,
        ],
        ImapClientProvider::class => [
            'alias' => WebklexImapClientProvider::class,
        ],
        MailReader::class => [
            'alias' => ImapMailReader::class,
        ],
        MailDraftStore::class => [
            'alias' => ImapMailDraftStore::class,
        ],
        MailDraftReceiptStore::class => [
            'alias' => DoctrineMailDraftReceiptStore::class,
        ],
        MailDraftWriter::class => [
            'alias' => SaveMailDraft::class,
        ],
        StandaloneMailDraftWriter::class => [
            'alias' => CreateStandaloneMailDraft::class,
        ],
        StandaloneMailDraftEditor::class => [
            'alias' => EditStandaloneMailDraft::class,
        ],
        MailDraftEditor::class => [
            'alias' => UpdateMailDraft::class,
        ],
        ProjectSearch::class => [
            'alias' => DoctrineProjectSearch::class,
        ],
        ProjectContextReader::class => [
            'alias' => DoctrineProjectContextReader::class,
        ],
        ProjectFollowupWriter::class => [
            'alias' => DoctrineProjectFollowupWriter::class,
        ],
        ProjectScheduleManager::class => [
            'alias' => DoctrineProjectScheduleManager::class,
        ],
    ],
]);
