<?php

declare(strict_types=1);

namespace App\Domain\Model\Session;

enum MediaResourceType: string
{
    case SOUNDTRACK = 'soundtrack';
    case AMBIENT_SOUND = 'ambient_sound';
    case SCORE = 'score';
    case VIDEO = 'video';
    case LINK = 'link';
    case TUTORIAL = 'tutorial';

    public function translationKey(): string
    {
        return match ($this) {
            self::SOUNDTRACK => 'sessions.media.type.soundtrack',
            self::AMBIENT_SOUND => 'sessions.media.type.ambient_sound',
            self::SCORE => 'sessions.media.type.score',
            self::VIDEO => 'sessions.media.type.video',
            self::LINK => 'sessions.media.type.link',
            self::TUTORIAL => 'sessions.media.type.tutorial',
        };
    }
}
