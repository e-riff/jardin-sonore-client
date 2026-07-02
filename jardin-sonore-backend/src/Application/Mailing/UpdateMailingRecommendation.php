<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class UpdateMailingRecommendation
{
    public function __construct(private MailingCampaignRepositoryInterface $mailingCampaignRepository)
    {
    }

    public function __invoke(
        MailingCampaign $mailingCampaign,
        Uuid $recommendationUuid,
        UpdateMailingRecommendationInput $input,
    ): bool {
        foreach ($mailingCampaign->getRecommendations() as $recommendation) {
            if (!$recommendation->getUuid()->equals($recommendationUuid)) {
                continue;
            }

            $recommendation->updateContent($input->title, $input->tag, $input->text);
            $recommendation->updateLink($input->url, $input->linkLabel);

            if ($input->active) {
                $recommendation->activate();
            } else {
                $recommendation->deactivate();
            }

            $mailingCampaign->replaceRecommendations($mailingCampaign->getRecommendations());
            $this->mailingCampaignRepository->save($mailingCampaign);

            return true;
        }

        return false;
    }
}
