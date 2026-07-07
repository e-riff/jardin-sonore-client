<?php

declare(strict_types=1);

namespace App\Application\ContentCatalog;

use App\Domain\Model\ContentCatalog\Instrument;
use App\Domain\Repository\InstrumentRepositoryInterface;
use App\Domain\Repository\InstrumentTagRepositoryInterface;
use InvalidArgumentException;

final readonly class CreateInstrument
{
    public function __construct(
        private InstrumentRepositoryInterface $instrumentRepository,
        private InstrumentTagRepositoryInterface $instrumentTagRepository,
    ) {
    }

    public function __invoke(SaveInstrumentInput $input): Instrument
    {
        $instrument = new Instrument(
            name: $input->name,
            tuning: $input->tuning,
            quantity: $input->quantity,
            notes: $input->notes,
            tags: $this->resolveTags($input->tagUuids),
            active: $input->active,
        );

        $this->instrumentRepository->save($instrument);

        return $instrument;
    }

    /**
     * @param list<string> $tagUuids
     *
     * @return list<\App\Domain\Model\ContentCatalog\InstrumentTag>
     */
    private function resolveTags(array $tagUuids): array
    {
        $normalizedTagUuids = array_values(array_unique(array_filter(array_map('trim', $tagUuids))));
        $tags = $this->instrumentTagRepository->findByUuids($normalizedTagUuids);

        if (count($tags) !== count($normalizedTagUuids)) {
            throw new InvalidArgumentException('One or more selected instrument tags could not be found.');
        }

        return $tags;
    }
}
