<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\SessionSequence;
use App\Domain\Model\Session\SessionSequenceSourceKind;
use App\Domain\Model\Session\SessionSummary;
use App\Domain\Repository\RepertoireItemRepositoryInterface;

final readonly class BuildFamilyElementGroups
{
    public function __construct(private RepertoireItemRepositoryInterface $repertoire)
    {
    }

    /**
     * @param list<SessionSummary> $sessions
     *
     * @return array<string, array<string, array{title: string, translationKey: string, repertoire: bool, versions: list<array{session: SessionSummary, sequence: SessionSequence, view: SessionSequenceView, available: bool}>}>>
     */
    public function __invoke(array $sessions): array
    {
        $groups = [];
        $repertoireItems = [];
        foreach ($sessions as $session) {
            foreach ($session->getSequences() as $sequence) {
                $isRepertoire = SessionSequenceSourceKind::REPERTOIRE_ITEM === $sequence->sourceKind;
                $repertoireItem = null;
                if ($isRepertoire && null !== $sequence->sourceUuid) {
                    $sourceKey = $sequence->sourceUuid->toRfc4122();
                    if (!array_key_exists($sourceKey, $repertoireItems)) {
                        $repertoireItems[$sourceKey] = $this->repertoire->findByUuid($sequence->sourceUuid);
                    }
                    $repertoireItem = $repertoireItems[$sourceKey];
                }
                $view = SessionSequenceView::fromDomain($sequence, $repertoireItem);
                $groupKey = $isRepertoire ? 'repertoire-' . ($sequence->sourceUuid ?? $sequence->uuid) : 'free-' . ($sequence->originUuid ?? $sequence->uuid);
                $type = $sequence->type->value;
                $groups[$type][$groupKey]['title'] ??= $view->title;
                $groups[$type][$groupKey]['translationKey'] = $sequence->type->translationKey();
                $groups[$type][$groupKey]['repertoire'] = $isRepertoire;
                $groups[$type][$groupKey]['versions'][] = ['session' => $session, 'sequence' => $sequence, 'view' => $view, 'available' => !$isRepertoire || null !== $repertoireItem];
            }
        }

        return $groups;
    }
}
