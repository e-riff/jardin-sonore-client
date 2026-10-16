<?php

declare(strict_types=1);

namespace App\Application\Commercial;

final class CommercialInterventionTitle
{
    public static function forOption(string $title, bool $isOption): string
    {
        $cleanTitle = self::withoutOptionMarker($title);

        return $isOption ? '[OPT] ' . $cleanTitle : $cleanTitle;
    }

    public static function withoutOptionMarker(string $title): string
    {
        return trim(preg_replace('/^(?:(?:\[OPT\]|\?{3})\s*)+/u', '', trim($title)) ?? $title);
    }
}
