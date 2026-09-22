<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Repository\InstrumentRepositoryInterface;
use App\Domain\Repository\MediaResourceRepositoryInterface;
use App\Domain\Repository\RepertoireItemRepositoryInterface;
use App\Domain\Repository\SessionRecommendationRepositoryInterface;
use App\Domain\Repository\SessionSummaryRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class GetSessionSummary
{
    public function __construct(
        private SessionSummaryRepositoryInterface $sessionSummaryRepository,
        private RepertoireItemRepositoryInterface $repertoireItemRepository,
        private InstrumentRepositoryInterface $instrumentRepository,
        private SessionRecommendationRepositoryInterface $sessionRecommendationRepository,
        private MediaResourceRepositoryInterface $mediaResourceRepository,
    ) {
    }

    public function __invoke(Uuid $uuid): ?SessionSummaryView
    {
        $sessionSummary = $this->sessionSummaryRepository->findByUuid($uuid);

        return null === $sessionSummary ? null : SessionSummaryView::fromDomain(
            $sessionSummary,
            $this->repertoireItemRepository,
            $this->instrumentRepository,
            $this->sessionRecommendationRepository,
            $this->mediaResourceRepository,
        );
    }
}
