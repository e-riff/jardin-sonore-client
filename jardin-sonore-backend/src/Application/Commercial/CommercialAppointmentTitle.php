<?php

declare(strict_types=1);

namespace App\Application\Commercial;

final class CommercialAppointmentTitle
{
    public static function hasMarker(string $title): bool
    {
        return 1 === preg_match('/^RDV(?:$|[\s[:punct:]·–—])/iu', trim($title));
    }

    public static function withMarker(string $title): string
    {
        $title = trim($title);

        return self::hasMarker($title) ? $title : "RDV · {$title}";
    }
}
