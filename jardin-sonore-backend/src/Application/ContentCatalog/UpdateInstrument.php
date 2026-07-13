<?php

declare(strict_types=1);

namespace App\Application\ContentCatalog;

use App\Domain\Repository\InstrumentRepositoryInterface;
use App\Domain\Repository\InstrumentTagRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class UpdateInstrument
{
    public function __construct(
        private InstrumentRepositoryInterface $instrumentRepository,
        private InstrumentTagRepositoryInterface $instrumentTagRepository,
    ) {
    }

    public function __invoke(Uuid $instrumentUuid, SaveInstrumentInput $input): void
    {
        $instrument = $this->instrumentRepository->findByUuid($instrumentUuid);

        if (null === $instrument) {
            throw new InvalidArgumentException('Instrument not found.');
        }

        $instrument->updateDetails(
            name: $input->name,
            tuning: $input->tuning,
            quantity: $input->quantity,
            notes: $input->notes,
        );
        $instrument->replaceTags($this->resolveTags($input->tagUuids));

        if ($input->active) {
            $instrument->activate();
        } else {
            $instrument->deactivate();
        }

        $this->instrumentRepository->save($instrument);
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
