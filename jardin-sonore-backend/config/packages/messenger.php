<?php

declare(strict_types=1);

use App\Application\Mailing\Message\SendMailingCampaignTestMessage;
use App\Application\Mailing\Message\SendMailingCampaignRecipientMessage;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $messengerConfig = [
        'failure_transport' => 'failed',
        'transports' => [
            'async' => '%env(MESSENGER_TRANSPORT_DSN)%',
            'failed' => 'doctrine://default?queue_name=failed',
        ],
        'routing' => [
            SendMailingCampaignTestMessage::class => 'async',
            SendMailingCampaignRecipientMessage::class => 'async',
        ],
    ];

    if ('test' === $containerConfigurator->env()) {
        $messengerConfig['transports']['async'] = 'in-memory://';
        $messengerConfig['transports']['failed'] = 'in-memory://';
    }

    $containerConfigurator->extension('framework', [
        'messenger' => $messengerConfig,
    ]);
};
