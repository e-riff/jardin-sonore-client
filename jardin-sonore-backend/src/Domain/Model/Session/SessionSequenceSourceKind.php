<?php

declare(strict_types=1);

namespace App\Domain\Model\Session;

enum SessionSequenceSourceKind: string
{
    case REPERTOIRE_ITEM = 'repertoire_item';
    case MEDIA_RESOURCE = 'media_resource';
    case SESSION_RECOMMENDATION = 'session_recommendation';
}
