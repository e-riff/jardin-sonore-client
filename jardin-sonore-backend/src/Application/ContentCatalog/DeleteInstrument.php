<?php

declare(strict_types=1);

namespace App\Application\ContentCatalog;

use App\Domain\Repository\InstrumentRepositoryInterface;
use App\Domain\Repository\SessionSummaryRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class DeleteInstrument
{
    public function __construct(
        private InstrumentRepositoryInterface $instrumentRepository,
        private SessionSummaryRepositoryInterface $sessionSummaryRepository,
    ) {
    }

    public function __invoke(Uuid $uuid): void
    {
        $instrument = $this->instrumentRepository->findByUuid($uuid);

        if (null === $instrument) {
            throw new InvalidArgumentException('Instrument not found.');
        }

        foreach ($this->sessionSummaryRepository->search() as $sessionSummary) {
            $sessionSummary->removeInstrument($uuid);
            $this->sessionSummaryRepository->save($sessionSummary);
        }

        $this->instrumentRepository->delete($instrument);
    }
}
