<?php

declare(strict_types=1);

namespace App\Application\Commercial;

final class CommercialAmountParser
{
    public static function euroCents(string $amount): ?int
    {
        $amount = str_replace(',', '.', trim($amount));
        if (1 !== preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            return null;
        }
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        if (9 < strlen($whole)) {
            return null;
        }

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }
}
