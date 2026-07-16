<?php

declare(strict_types=1);

namespace App\Domain\Model\Session;

enum RepertoireItemType: string
{
    case NURSERY_RHYME = 'nursery_rhyme';
    case FINGERPLAY = 'fingerplay';

    public function translationKey(): string
    {
        return match ($this) {
            self::NURSERY_RHYME => 'sessions.repertoire.type.nursery_rhyme',
            self::FINGERPLAY => 'sessions.repertoire.type.fingerplay',
        };
    }
}
