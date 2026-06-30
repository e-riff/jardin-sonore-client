<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Application\Mailing\Message\SendMailingCampaignTestMessage;
use App\Domain\Model\Mailing\MailingCampaign;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class SendMailingCampaignTest
{
    public function __construct(private MessageBusInterface $messageBus)
    {
    }

    public function __invoke(MailingCampaign $mailingCampaign, string $recipientEmail): void
    {
        $this->messageBus->dispatch(new SendMailingCampaignTestMessage(
            campaignUuid: $mailingCampaign->getUuid()->toRfc4122(),
            recipientEmail: $recipientEmail,
        ));
    }
}
