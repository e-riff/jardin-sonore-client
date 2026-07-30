<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\SessionSequence;
use App\Domain\Repository\SessionSummaryRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class UpdateSessionSequenceLocalSetting
{
    public function __construct(private SessionSummaryRepositoryInterface $sessionSummaryRepository)
    {
    }

    public function __invoke(Uuid $sessionUuid, Uuid $sequenceUuid, SessionSequenceLocalSetting $setting, ?string $value): void
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
                uuid: $sessionSequence->uuid, type: $sessionSequence->type, title: $sessionSequence->title, subtitle: $sessionSequence->subtitle,
                body: SessionSequenceLocalSetting::BODY === $setting ? trim((string) $value) : $sessionSequence->body,
                lyrics: $sessionSequence->lyrics, gestures: $sessionSequence->gestures,
                notes: SessionSequenceLocalSetting::NOTES === $setting ? $value : $sessionSequence->notes,
                primaryUrl: $sessionSequence->primaryUrl, secondaryUrl: $sessionSequence->secondaryUrl, imageUrl: $sessionSequence->imageUrl,
                showLyricsByDefault: $sessionSequence->showLyricsByDefault, sourceUuid: $sessionSequence->sourceUuid,
                sourceKind: $sessionSequence->sourceKind, sourceTitle: $sessionSequence->sourceTitle,
                role: SessionSequenceLocalSetting::ROLE === $setting ? $value : $sessionSequence->role,
                instrumentUuids: $sessionSequence->instrumentUuids, media: $sessionSequence->getMedia(),
            ));
            $this->sessionSummaryRepository->save($sessionSummary);

            return;
        }

        throw new InvalidArgumentException('Session sequence not found.');
    }
}
