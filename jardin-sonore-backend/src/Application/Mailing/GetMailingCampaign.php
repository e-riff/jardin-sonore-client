<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class GetMailingCampaign
{
    public function __construct(private MailingCampaignRepositoryInterface $mailingCampaignRepository)
    {
    }

    public function __invoke(Uuid $uuid): ?MailingCampaign
    {
        return $this->mailingCampaignRepository->findByUuid($uuid);
    }
}
