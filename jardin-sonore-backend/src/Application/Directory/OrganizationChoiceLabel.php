<?php

declare(strict_types=1);

namespace App\Application\Directory;

use App\Infrastructure\Doctrine\Entity\OrganizationEntity;

final class OrganizationChoiceLabel
{
    public static function forEntity(OrganizationEntity $organizationEntity): string
    {
        $municipality = $organizationEntity->getMunicipalitySummary();

        return '—' === $municipality
            ? $organizationEntity->getName()
            : "{$organizationEntity->getName()} — {$municipality}";
    }

    public static function format(string $name, ?string $postalCode, ?string $city): string
    {
        return implode(' — ', array_filter(array_map('trim', [$name, $postalCode ?? '', $city ?? '']), static fn (string $part): bool => '' !== $part));
    }
}
