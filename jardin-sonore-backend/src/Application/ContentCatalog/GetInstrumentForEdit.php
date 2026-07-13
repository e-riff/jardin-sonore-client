<?php

declare(strict_types=1);

namespace App\Application\ContentCatalog;

use App\Domain\Repository\InstrumentRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class GetInstrumentForEdit
{
    public function __construct(private InstrumentRepositoryInterface $instrumentRepository)
    {
    }

    public function __invoke(Uuid $uuid): ?InstrumentEditView
    {
        $instrument = $this->instrumentRepository->findByUuid($uuid);

        return null === $instrument ? null : InstrumentEditView::fromInstrument($instrument);
    }
}
