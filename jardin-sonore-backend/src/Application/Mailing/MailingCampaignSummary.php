<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\MailingCampaignStatus;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class MailingCampaignSummary
{
    public function __construct(
        public Uuid $uuid,
        public string $internalTitle,
        public string $emailSubject,
        public MailingCampaignStatus $status,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
