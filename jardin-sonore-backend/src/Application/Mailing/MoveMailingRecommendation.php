<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class MoveMailingRecommendation
{
    public function __construct(private MailingCampaignRepositoryInterface $mailingCampaignRepository)
    {
    }

    public function __invoke(MailingCampaign $mailingCampaign, Uuid $recommendationUuid, int $offset): bool
    {
        if (!in_array($offset, [-1, 1], true)) {
            return false;
        }

        $recommendations = $mailingCampaign->getRecommendations();
        $currentIndex = null;

        foreach ($recommendations as $index => $recommendation) {
            if ($recommendation->getUuid()->equals($recommendationUuid)) {
                $currentIndex = $index;
                break;
            }
        }

        if (null === $currentIndex) {
            return false;
        }

        $targetIndex = $currentIndex + $offset;

        if (!isset($recommendations[$targetIndex])) {
            return true;
        }

        [$recommendations[$currentIndex], $recommendations[$targetIndex]] = [
            $recommendations[$targetIndex],
            $recommendations[$currentIndex],
        ];

        foreach ($recommendations as $position => $recommendation) {
            $recommendation->moveToPosition($position + 1);
        }

        $mailingCampaign->replaceRecommendations($recommendations);
        $this->mailingCampaignRepository->save($mailingCampaign);

        return true;
    }
}
