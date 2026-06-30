<?php

declare(strict_types=1);

namespace App\Application\Mailing;

interface NewsletterAudienceOptionsProviderInterface
{
    /**
     * @return array<string, string>
     */
    public function getTagChoices(): array;

    /**
     * @return array<string, string>
     */
    public function getRegionChoices(): array;

    /**
     * @return array<string, array<string, string>>
     */
    public function getDepartmentChoices(): array;

    /**
     * @return array<string, array<string, string>>
     */
    public function getMunicipalityChoices(): array;

    /**
     * @param list<string> $inseeCodes
     *
     * @return list<string>
     */
    public function getExistingMunicipalityInseeCodes(array $inseeCodes): array;

    /**
     * @param list<string> $inseeCodes
     *
     * @return array<string, string>
     */
    public function getMunicipalityLabelsByInseeCodes(array $inseeCodes): array;

    /**
     * @return array{
     *     results: list<array{text: string, value: string, group_by: string}>,
     *     optgroups: list<array{value: string, label: string}>,
     *     has_next_page: bool
     * }
     */
    public function searchMunicipalityAutocompleteChoices(string $query, int $page, int $limit): array;
}
