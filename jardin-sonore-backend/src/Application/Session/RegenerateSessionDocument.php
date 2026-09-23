<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Repository\SessionSummaryRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class RegenerateSessionDocument
{
    public function __construct(private SessionSummaryRepositoryInterface $sessionSummaryRepository)
    {
    }

    public function __invoke(Uuid $uuid): void
    {
        $sessionSummary = $this->sessionSummaryRepository->findByUuid($uuid);
        if (null === $sessionSummary) {
            throw new InvalidArgumentException('Session summary not found.');
        }

        $sessionSummary->markDocumentPending();
        $this->sessionSummaryRepository->save($sessionSummary);
    }
}
