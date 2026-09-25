<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Repository\SessionSummaryRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class SetSessionPublication
{
    public function __construct(private SessionSummaryRepositoryInterface $sessionSummaryRepository)
    {
    }

    public function __invoke(Uuid $uuid, bool $published): ?bool
    {
        $sessionSummary = $this->sessionSummaryRepository->findByUuid($uuid);
        if (null === $sessionSummary) {
            return null;
        }

        $sessionSummary->setPublished($published);
        $this->sessionSummaryRepository->save($sessionSummary, false);

        return $sessionSummary->isPublished();
    }
}
