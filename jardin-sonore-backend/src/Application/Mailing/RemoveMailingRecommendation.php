<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class RemoveMailingRecommendation
{
    public function __construct(private MailingCampaignRepositoryInterface $mailingCampaignRepository)
    {
    }

    public function __invoke(MailingCampaign $mailingCampaign, Uuid $recommendationUuid): bool
    {
        $recommendations = array_values(array_filter(
            $mailingCampaign->getRecommendations(),
            static fn ($recommendation): bool => !$recommendation->getUuid()->equals($recommendationUuid),
        ));

        if (count($recommendations) === count($mailingCampaign->getRecommendations())) {
            return false;
        }

        foreach ($recommendations as $position => $recommendation) {
            $recommendation->moveToPosition($position + 1);
        }

        $mailingCampaign->replaceRecommendations($recommendations);
        $this->mailingCampaignRepository->save($mailingCampaign);

        return true;
    }
}
