<?php

declare(strict_types=1);

namespace App\Application\Directory;

use App\Domain\Model\AddressBook\OrganizationType;

final readonly class DirectoryOrganizationTypeResolver
{
    public function resolve(DirectoryEstablishmentImportItem $item): ?OrganizationType
    {
        $directOrganizationType = OrganizationType::tryFrom(mb_strtolower(trim($item->type)));
        if (null !== $directOrganizationType) {
            return $directOrganizationType;
        }

        $importType = strtoupper($item->type);
        $normalizedType = $this->normalizeText($item->type);
        $normalizedName = $this->normalizeText($item->name);

        return match (true) {
            'EAJE' === $importType => OrganizationType::CRECHE,
            in_array($importType, ['MJC', 'MPT'], true) => OrganizationType::MJC,
            str_contains($normalizedType, 'cafe') && str_contains($normalizedType, 'parent'),
            str_contains($normalizedName, 'cafe poussette'),
            str_contains($normalizedName, 'cafe des enfants') => OrganizationType::CAFE_PARENTS,
            str_contains($normalizedType, 'librairie jeunesse') => OrganizationType::LIBRAIRIE_JEUNESSE,
            str_contains($normalizedType, 'festival') || str_contains($normalizedType, 'evenement') => OrganizationType::FESTIVAL_EVENEMENT,
            str_contains($normalizedType, 'salle spectacle')
                || str_contains($normalizedType, 'salle de spectacle')
                || str_contains($normalizedType, 'theatre')
                || str_contains($normalizedType, 'scene nationale')
                || 'cdn' === $normalizedType
                || str_contains($normalizedType, 'equipement culturel') => OrganizationType::SALLE_SPECTACLE,
            str_contains($normalizedType, 'ludotheque'), str_contains($normalizedName, 'ludotheque') => OrganizationType::LUDOTHEQUE,
            str_contains($normalizedType, 'mediatheque') => OrganizationType::MEDIATHEQUE,
            in_array($normalizedType, ['centre', 'centre social', 'centre de loisirs', 'centre loisirs', 'accueil de loisirs', 'alsh'], true) => OrganizationType::CENTRE,
            in_array($normalizedType, ['laep', 'lieu de parentalite', 'lieux enfants parents'], true) => OrganizationType::LIEU_PARENTALITE,
            str_contains($normalizedName, 'mairie') || str_contains($normalizedName, 'ville de ') => OrganizationType::MAIRIE,
            in_array($normalizedType, ['rpe', 'ram', 'relais petite enfance', 'relais assistantes maternelles'], true),
            1 === preg_match('/\b(ram|rpe)\b/', $normalizedName),
            str_contains($normalizedName, 'relais petite enfance'),
            str_contains($normalizedName, 'relais assistantes maternelles') => OrganizationType::RPE,
            str_contains($normalizedName, 'mam') => OrganizationType::MAM,
            str_contains($normalizedName, 'garderie') => OrganizationType::GARDERIE,
            1 === preg_match('/\b(mjc|maison pour tous)\b/', $normalizedName) => OrganizationType::MJC,
            1 === preg_match('/\b(alsh|centre de loisirs|accueil de loisirs)\b/', $normalizedName) => OrganizationType::CENTRE,
            1 === preg_match('/\b(laep|lieu de parentalite)\b/', $normalizedName) => OrganizationType::LIEU_PARENTALITE,
            str_contains($normalizedName, 'centre social') => OrganizationType::CENTRE,
            default => null,
        };
    }

    private function normalizeText(?string $value): string
    {
        $value = null === $value ? '' : trim($value);

        if ('' === $value) {
            return '';
        }

        $asciiValue = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $asciiValue = false === $asciiValue ? $value : $asciiValue;
        $asciiValue = mb_strtolower($asciiValue);
        $asciiValue = preg_replace('/[^a-z0-9]+/', ' ', $asciiValue) ?? $asciiValue;

        return trim(preg_replace('/\s+/', ' ', $asciiValue) ?? $asciiValue);
    }
}
