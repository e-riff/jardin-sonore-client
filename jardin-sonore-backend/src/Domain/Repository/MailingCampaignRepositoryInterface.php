<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Mailing\MailingCampaign;
use Symfony\Component\Uid\Uuid;

interface MailingCampaignRepositoryInterface
{
    public function findByUuid(Uuid $uuid): ?MailingCampaign;

    /**
     * @return list<MailingCampaign>
     */
    public function findAllOrderedByCreatedAtDesc(): array;

    public function save(MailingCampaign $mailingCampaign): void;
}
