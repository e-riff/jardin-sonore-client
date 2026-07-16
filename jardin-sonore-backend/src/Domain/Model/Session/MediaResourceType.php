<?php

declare(strict_types=1);

namespace App\Domain\Model\Session;

enum MediaResourceType: string
{
    case SOUNDTRACK = 'soundtrack';
    case VIDEO = 'video';
    case LINK = 'link';

    public function translationKey(): string
    {
        return match ($this) {
            self::SOUNDTRACK => 'sessions.media.type.soundtrack',
            self::VIDEO => 'sessions.media.type.video',
            self::LINK => 'sessions.media.type.link',
        };
    }
}
