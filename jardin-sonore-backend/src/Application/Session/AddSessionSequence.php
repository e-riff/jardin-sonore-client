<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\SessionSequence;
use App\Domain\Repository\SessionSummaryRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class AddSessionSequence
{
    public function __construct(private SessionSummaryRepositoryInterface $sessionSummaryRepository)
    {
    }

    public function __invoke(Uuid $sessionUuid, SaveSessionSequenceInput $saveSessionSequenceInput): SessionSequence
    {
        $sessionSummary = $this->sessionSummaryRepository->findByUuid($sessionUuid);

        if (null === $sessionSummary) {
            throw new InvalidArgumentException('Session summary not found.');
        }

        $sessionSequence = new SessionSequence(
            uuid: Uuid::v4(),
            type: $saveSessionSequenceInput->type,
            title: trim($saveSessionSequenceInput->title),
            subtitle: $saveSessionSequenceInput->subtitle,
            body: trim($saveSessionSequenceInput->body),
            lyrics: $saveSessionSequenceInput->lyrics,
            gestures: $saveSessionSequenceInput->gestures,
            notes: $saveSessionSequenceInput->notes,
            primaryUrl: $saveSessionSequenceInput->primaryUrl,
            secondaryUrl: $saveSessionSequenceInput->secondaryUrl,
            imageUrl: $saveSessionSequenceInput->imageUrl,
            showLyricsByDefault: $saveSessionSequenceInput->showLyricsByDefault,
            role: $saveSessionSequenceInput->role,
            sourceUuid: $saveSessionSequenceInput->sourceUuid,
            sourceKind: $saveSessionSequenceInput->sourceKind,
            sourceTitle: $saveSessionSequenceInput->sourceTitle,
            instrumentUuids: $saveSessionSequenceInput->instrumentUuids,
        );

        $sessionSummary->addSequence($sessionSequence);
        $this->sessionSummaryRepository->save($sessionSummary);

        return $sessionSequence;
    }
}
