<?php

declare(strict_types=1);

namespace App\Application\Mailing\MessageHandler;

use App\Application\Mailing\Message\SendMailingCampaignTestMessage;
use App\Application\Mailing\NewsletterMailSenderInterface;
use App\Application\Mailing\NewsletterRendererInterface;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class SendMailingCampaignTestMessageHandler
{
    public function __construct(
        private MailingCampaignRepositoryInterface $mailingCampaignRepository,
        private NewsletterRendererInterface $newsletterRenderer,
        private NewsletterMailSenderInterface $newsletterMailSender,
    ) {
    }

    public function __invoke(SendMailingCampaignTestMessage $sendMailingCampaignTestMessage): void
    {
        if (!Uuid::isValid($sendMailingCampaignTestMessage->campaignUuid)) {
            return;
        }

        $mailingCampaign = $this->mailingCampaignRepository->findByUuid(Uuid::fromString($sendMailingCampaignTestMessage->campaignUuid));

        if (null === $mailingCampaign) {
            return;
        }

        $renderedNewsletter = $this->newsletterRenderer->render($mailingCampaign);
        $this->newsletterMailSender->sendTest($renderedNewsletter, $sendMailingCampaignTestMessage->recipientEmail);
        $mailingCampaign->markTestSent();
        $this->mailingCampaignRepository->save($mailingCampaign);
    }
}
