<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\SessionSequence;
use App\Domain\Model\Session\SessionSequenceSourceKind;
use App\Domain\Model\Session\SessionSummary;
use App\Domain\Repository\RepertoireItemRepositoryInterface;
use App\Domain\Repository\SessionFamilyRepositoryInterface;
use App\Domain\Repository\SessionSummaryRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class ComposeFamilySession
{
    public function __construct(
        private SessionFamilyRepositoryInterface $families,
        private SessionSummaryRepositoryInterface $sessions,
        private RepertoireItemRepositoryInterface $repertoire,
    ) {
    }

    /** @param list<array{session: string, sequence: string, instructions: bool}> $selection */
    public function __invoke(Uuid $familyUuid, string $title, DateTimeImmutable $date, array $selection): SessionSummary
    {
        if (null === $this->families->findByUuid($familyUuid) || '' === trim($title) || 255 < mb_strlen(trim($title)) || [] === $selection) {
            throw new InvalidArgumentException('Invalid family or selection.');
        }
        $sources = [];
        foreach ($this->families->sessions($familyUuid) as $session) {
            foreach ($session->getSequences() as $sequence) {
                $sources[$session->getUuid()->toRfc4122() . ':' . $sequence->uuid->toRfc4122()] = $sequence;
            }
        }
        $copies = [];
        $seen = [];
        foreach ($selection as $item) {
            $source = $sources[$item['session'] . ':' . $item['sequence']] ?? null;
            if (!$source instanceof SessionSequence) {
                throw new InvalidArgumentException('Session element no longer available.');
            }
            $isRepertoire = SessionSequenceSourceKind::REPERTOIRE_ITEM === $source->sourceKind;
            if ($isRepertoire && (null === $source->sourceUuid || null === $this->repertoire->findByUuid($source->sourceUuid))) {
                throw new InvalidArgumentException('Repertoire element no longer available.');
            }
            $key = $isRepertoire ? 'repertoire:' . $source->sourceUuid->toRfc4122() : 'free:' . ($source->originUuid ?? $source->uuid)->toRfc4122();
            if (isset($seen[$key])) {
                throw new InvalidArgumentException('Select only one version of each element.');
            }
            $seen[$key] = true;
            $payload = $source->toArray();
            $payload['uuid'] = Uuid::v4()->toRfc4122();
            if (!$isRepertoire) {
                $payload['originUuid'] = ($source->originUuid ?? $source->uuid)->toRfc4122();
            } elseif (!$item['instructions']) {
                $payload['body'] = '';
                $payload['role'] = null;
                $payload['instrumentUuids'] = [];
                $payload['media'] = [];
            }
            $copies[] = SessionSequence::fromArray($payload);
        }
        $session = new SessionSummary(trim($title), $date, sequences: $copies, familyUuid: $familyUuid);
        $this->sessions->save($session);

        return $session;
    }
}
