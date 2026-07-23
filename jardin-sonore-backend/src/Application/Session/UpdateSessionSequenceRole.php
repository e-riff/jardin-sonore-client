<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\SessionSequence;
use App\Domain\Repository\SessionSummaryRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class UpdateSessionSequenceRole
{
    public function __construct(private SessionSummaryRepositoryInterface $sessionSummaryRepository)
    {
    }

    public function __invoke(Uuid $sessionUuid, Uuid $sequenceUuid, ?string $role): void
    {
        $sessionSummary = $this->sessionSummaryRepository->findByUuid($sessionUuid);

        if (null === $sessionSummary) {
            throw new InvalidArgumentException('Session summary not found.');
        }

        foreach ($sessionSummary->getSequences() as $sessionSequence) {
            if (!$sessionSequence->uuid->equals($sequenceUuid)) {
                continue;
            }

            $sessionSummary->replaceSequence(new SessionSequence(
                uuid: $sessionSequence->uuid,
                type: $sessionSequence->type,
                title: $sessionSequence->title,
                subtitle: $sessionSequence->subtitle,
                body: $sessionSequence->body,
                lyrics: $sessionSequence->lyrics,
                gestures: $sessionSequence->gestures,
                notes: $sessionSequence->notes,
                primaryUrl: $sessionSequence->primaryUrl,
                secondaryUrl: $sessionSequence->secondaryUrl,
                imageUrl: $sessionSequence->imageUrl,
                showLyricsByDefault: $sessionSequence->showLyricsByDefault,
                sourceUuid: $sessionSequence->sourceUuid,
                sourceKind: $sessionSequence->sourceKind,
                sourceTitle: $sessionSequence->sourceTitle,
                role: $role,
                instrumentUuids: $sessionSequence->instrumentUuids,
            ));
            $this->sessionSummaryRepository->save($sessionSummary);

            return;
        }

        throw new InvalidArgumentException('Session sequence not found.');
    }
}
