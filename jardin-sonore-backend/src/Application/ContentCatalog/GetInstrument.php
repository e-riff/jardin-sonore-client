<?php

declare(strict_types=1);

namespace App\Application\ContentCatalog;

use App\Domain\Model\ContentCatalog\Instrument;
use App\Domain\Repository\InstrumentRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class GetInstrument
{
    public function __construct(private InstrumentRepositoryInterface $instrumentRepository)
    {
    }

    public function __invoke(Uuid $uuid): ?Instrument
    {
        return $this->instrumentRepository->findByUuid($uuid);
    }
}
