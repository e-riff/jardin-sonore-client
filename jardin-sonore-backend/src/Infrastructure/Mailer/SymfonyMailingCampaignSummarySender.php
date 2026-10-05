<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailer;

use App\Application\Mailing\MailingCampaignSummarySenderInterface;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\MailingCampaignStatus;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class SymfonyMailingCampaignSummarySender implements MailingCampaignSummarySenderInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        #[Autowire('%app.mailing.from_email%')]
        private string $fromEmail,
        #[Autowire('%app.mailing.from_name%')]
        private string $fromName,
    ) {
    }

    public function send(MailingCampaign $mailingCampaign, array $deliveryCounts): void
    {
        $statusTranslationKey = match ($mailingCampaign->getStatus()) {
            MailingCampaignStatus::DELIVERY_SENT => 'mailing_summary.status.sent',
            MailingCampaignStatus::DELIVERY_FAILED => 'mailing_summary.status.failed',
            MailingCampaignStatus::DELIVERY_STOPPED => 'mailing_summary.status.stopped',
            default => throw new LogicException('A completion summary requires a terminal mailing campaign status.'),
        };
        $statusLabel = $this->translator->trans(
            $statusTranslationKey,
            [],
            'service_email',
            'fr',
        );
        $countLabels = [
            'sent' => 'mailing_summary.count.sent',
            'failed' => 'mailing_summary.count.failed',
            'cancelled' => 'mailing_summary.count.cancelled',
            'pending' => 'mailing_summary.count.pending',
            'processing' => 'mailing_summary.count.processing',
        ];
        $countLines = [];

        foreach ($countLabels as $status => $translationKey) {
            $countLines[] = $this->translator->trans($translationKey, [], 'service_email', 'fr') . ': ' . ($deliveryCounts[$status] ?? 0);
        }

        $email = (new Email())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($this->fromEmail)
            ->subject($this->translator->trans(
                'mailing_summary.subject',
                ['%campaign%' => $mailingCampaign->getInternalTitle()],
                'service_email',
                'fr',
            ))
            ->text(implode("\n", [
                $this->translator->trans('mailing_summary.intro', [], 'service_email', 'fr'),
                '',
                $this->translator->trans('mailing_summary.campaign', [], 'service_email', 'fr') . ': ' . $mailingCampaign->getInternalTitle(),
                $this->translator->trans('mailing_summary.email_subject', [], 'service_email', 'fr') . ': ' . $mailingCampaign->getEmailSubject(),
                $this->translator->trans('mailing_summary.result', [], 'service_email', 'fr') . ': ' . $statusLabel,
                $this->translator->trans('mailing_summary.total', [], 'service_email', 'fr') . ': ' . array_sum($deliveryCounts),
                '',
                ...$countLines,
            ]));

        $this->mailer->send($email);
    }
}
