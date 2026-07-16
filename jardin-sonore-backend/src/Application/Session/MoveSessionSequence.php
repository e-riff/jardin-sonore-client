<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\SessionSummary;
use App\Domain\Repository\SessionSummaryRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class MoveSessionSequence
{
    public function __construct(private SessionSummaryRepositoryInterface $sessionSummaryRepository)
    {
    }

    public function up(Uuid $sessionUuid, Uuid $sequenceUuid): void
    {
        $sessionSummary = $this->getSessionSummary($sessionUuid);
        $sessionSummary->moveSequenceUp($sequenceUuid);
        $this->sessionSummaryRepository->save($sessionSummary);
    }

    public function down(Uuid $sessionUuid, Uuid $sequenceUuid): void
    {
        $sessionSummary = $this->getSessionSummary($sessionUuid);
        $sessionSummary->moveSequenceDown($sequenceUuid);
        $this->sessionSummaryRepository->save($sessionSummary);
    }

    private function getSessionSummary(Uuid $sessionUuid): SessionSummary
    {
        $sessionSummary = $this->sessionSummaryRepository->findByUuid($sessionUuid);

        if (null === $sessionSummary) {
            throw new InvalidArgumentException('Session summary not found.');
        }

        return $sessionSummary;
    }
}
