<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use App\Application\Mailing\Message\SendMailingCampaignRecipientMessage;
use App\Application\Mailing\Message\SendMailingCampaignTestMessage;
use App\Application\Session\Message\GenerateSessionDocumentMessage;

return App::config([
    'framework' => [
        'messenger' => [
            'failure_transport' => 'failed',
            'transports' => [
                'async' => '%env(MESSENGER_TRANSPORT_DSN)%',
                'failed' => 'doctrine://default?queue_name=failed',
            ],
            'routing' => [
                SendMailingCampaignTestMessage::class => 'async',
                SendMailingCampaignRecipientMessage::class => 'async',
                GenerateSessionDocumentMessage::class => 'async',
            ],
        ],
    ],
    'when@test' => [
        'framework' => [
            'messenger' => [
                'transports' => [
                    'async' => 'in-memory://',
                    'failed' => 'in-memory://',
                ],
            ],
        ],
    ],
]);
