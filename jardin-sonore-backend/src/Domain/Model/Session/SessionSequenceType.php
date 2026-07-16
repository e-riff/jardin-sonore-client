<?php

declare(strict_types=1);

namespace App\Domain\Model\Session;

enum SessionSequenceType: string
{
    case WARMUP = 'warmup';
    case NURSERY_RHYME = 'nursery_rhyme';
    case FINGERPLAY = 'fingerplay';
    case SOUNDTRACK = 'soundtrack';
    case MANIPULATION = 'manipulation';
    case MOVEMENT = 'movement';
    case CLOSING = 'closing';
    case FREE = 'free';

    public function translationKey(): string
    {
        return match ($this) {
            self::WARMUP => 'sessions.sequence.type.warmup',
            self::NURSERY_RHYME => 'sessions.sequence.type.nursery_rhyme',
            self::FINGERPLAY => 'sessions.sequence.type.fingerplay',
            self::SOUNDTRACK => 'sessions.sequence.type.soundtrack',
            self::MANIPULATION => 'sessions.sequence.type.manipulation',
            self::MOVEMENT => 'sessions.sequence.type.movement',
            self::CLOSING => 'sessions.sequence.type.closing',
            self::FREE => 'sessions.sequence.type.free',
        };
    }

    public static function fromRepertoireItemType(RepertoireItemType $repertoireItemType): self
    {
        return match ($repertoireItemType) {
            RepertoireItemType::NURSERY_RHYME => self::NURSERY_RHYME,
            RepertoireItemType::FINGERPLAY => self::FINGERPLAY,
        };
    }
}
