<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailing;

use App\Application\Mailing\NewsletterAudienceOptionsProviderInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineNewsletterAudienceOptionsProvider implements NewsletterAudienceOptionsProviderInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function getTagChoices(): array
    {
        /** @var list<array{label: string, uuid: string}> $tags */
        $tags = $this->connection->fetchAllAssociative(
            'SELECT label, uuid
            FROM tag
            ORDER BY label',
        );

        $choices = [];

        foreach ($tags as $tag) {
            $choices[$tag['label']] = Uuid::fromBinary($tag['uuid'])->toRfc4122();
        }

        return $choices;
    }

    public function getRegionChoices(): array
    {
        return $this->connection->fetchAllKeyValue(
            "SELECT CONCAT(code, ' — ', name), code
            FROM region
            ORDER BY code",
        );
    }

    public function getDepartmentChoices(): array
    {
        $preferredDepartmentCodes = ['42', '69', '43', '07'];

        /** @var list<array{code: string, name: string}> $departments */
        $departments = $this->connection->fetchAllAssociative(
            "SELECT code, name
            FROM department
            ORDER BY
                CASE code
                    WHEN '42' THEN 0
                    WHEN '69' THEN 1
                    WHEN '43' THEN 2
                    WHEN '07' THEN 3
                    ELSE 4
                END,
                code",
        );

        $choices = [
            'mailing.audience.form.department_group_preferred' => [],
            'mailing.audience.form.department_group_all' => [],
        ];

        foreach ($departments as $department) {
            $group = in_array($department['code'], $preferredDepartmentCodes, true)
                ? 'mailing.audience.form.department_group_preferred'
                : 'mailing.audience.form.department_group_all';

            $choices[$group]["{$department['code']} — {$department['name']}"] = $department['code'];
        }

        return $choices;
    }

    public function getMunicipalityChoices(): array
    {
        /** @var list<array{
         *     insee_code: string,
         *     name: string,
         *     postal_code: ?string,
         *     department_code: string,
         *     department_name: string
         * }> $municipalities
         */
        $municipalities = $this->connection->fetchAllAssociative(
            "SELECT DISTINCT
                municipality.insee_code,
                municipality.name,
                municipality.postal_code,
                department.code AS department_code,
                department.name AS department_name
            FROM municipality
            INNER JOIN department ON department.id = municipality.department_id
            WHERE municipality.insee_code IS NOT NULL
            ORDER BY
                CASE department.code
                    WHEN '42' THEN 0
                    WHEN '69' THEN 1
                    WHEN '43' THEN 2
                    WHEN '07' THEN 3
                    ELSE 4
                END,
                department.code,
                municipality.name,
                municipality.postal_code",
        );

        $choices = [];

        foreach ($municipalities as $municipality) {
            $departmentLabel = "{$municipality['department_code']} — {$municipality['department_name']}";
            $municipalityLabel = null !== $municipality['postal_code'] && '' !== $municipality['postal_code']
                ? "{$municipality['postal_code']} — {$municipality['name']}"
                : $municipality['name'];
            $choices[$departmentLabel][$municipalityLabel] = $municipality['insee_code'];
        }

        return $choices;
    }

    public function getExistingMunicipalityInseeCodes(array $inseeCodes): array
    {
        $inseeCodes = $this->normalizeInseeCodes($inseeCodes);

        if ([] === $inseeCodes) {
            return [];
        }

        /** @var list<string> $existingInseeCodes */
        $existingInseeCodes = $this->connection->fetchFirstColumn(
            'SELECT municipality.insee_code
            FROM municipality
            WHERE municipality.insee_code IN (:inseeCodes)',
            [
                'inseeCodes' => $inseeCodes,
            ],
            [
                'inseeCodes' => ArrayParameterType::STRING,
            ],
        );
        $existingInseeCodes = array_flip($existingInseeCodes);

        return array_values(array_filter(
            $inseeCodes,
            static fn (string $inseeCode): bool => isset($existingInseeCodes[$inseeCode]),
        ));
    }

    public function getMunicipalityLabelsByInseeCodes(array $inseeCodes): array
    {
        $inseeCodes = $this->normalizeInseeCodes($inseeCodes);

        if ([] === $inseeCodes) {
            return [];
        }

        /** @var list<array{insee_code: string, name: string, postal_code: ?string}> $municipalities */
        $municipalities = $this->connection->fetchAllAssociative(
            'SELECT municipality.insee_code, municipality.name, municipality.postal_code
            FROM municipality
            WHERE municipality.insee_code IN (:inseeCodes)',
            [
                'inseeCodes' => $inseeCodes,
            ],
            [
                'inseeCodes' => ArrayParameterType::STRING,
            ],
        );
        $labelsByInseeCode = [];

        foreach ($municipalities as $municipality) {
            $labelsByInseeCode[$municipality['insee_code']] = $this->municipalityLabel($municipality);
        }

        return $labelsByInseeCode;
    }

    public function searchMunicipalityAutocompleteChoices(string $query, int $page, int $limit): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $offset = ($page - 1) * $limit;
        $query = trim($query);
        $likeQuery = '%' . addcslashes($query, '%_\\') . '%';

        /** @var list<array{
         *     insee_code: string,
         *     name: string,
         *     postal_code: ?string,
         *     department_code: string,
         *     department_name: string
         * }> $municipalities
         */
        $municipalities = $this->connection->fetchAllAssociative(
            "SELECT
                municipality.insee_code,
                municipality.name,
                municipality.postal_code,
                department.code AS department_code,
                department.name AS department_name
            FROM municipality
            INNER JOIN department ON department.id = municipality.department_id
            WHERE municipality.insee_code IS NOT NULL
                AND (
                    :query = ''
                    OR municipality.name LIKE :likeQuery
                    OR municipality.postal_code LIKE :likeQuery
                    OR municipality.insee_code LIKE :likeQuery
                    OR department.code LIKE :likeQuery
                )
            ORDER BY
                CASE department.code
                    WHEN '42' THEN 0
                    WHEN '69' THEN 1
                    WHEN '43' THEN 2
                    WHEN '07' THEN 3
                    ELSE 4
                END,
                department.code,
                municipality.name,
                municipality.postal_code
            LIMIT :limitPlusOne OFFSET :offset",
            [
                'query' => $query,
                'likeQuery' => $likeQuery,
                'limitPlusOne' => $limit + 1,
                'offset' => $offset,
            ],
            [
                'limitPlusOne' => ParameterType::INTEGER,
                'offset' => ParameterType::INTEGER,
            ],
        );

        $hasNextPage = count($municipalities) > $limit;
        $municipalities = array_slice($municipalities, 0, $limit);
        $optgroups = [];
        $results = [];

        foreach ($municipalities as $municipality) {
            $departmentLabel = "{$municipality['department_code']} — {$municipality['department_name']}";
            $optgroups[$departmentLabel] = [
                'value' => $departmentLabel,
                'label' => $departmentLabel,
            ];
            $results[] = [
                'text' => $this->municipalityLabel($municipality),
                'value' => $municipality['insee_code'],
                'group_by' => $departmentLabel,
            ];
        }

        return [
            'results' => $results,
            'optgroups' => array_values($optgroups),
            'has_next_page' => $hasNextPage,
        ];
    }

    /**
     * @param list<mixed> $inseeCodes
     *
     * @return list<string>
     */
    private function normalizeInseeCodes(array $inseeCodes): array
    {
        return array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $inseeCode): string => is_string($inseeCode) ? trim($inseeCode) : '',
                $inseeCodes,
            ),
            static fn (string $inseeCode): bool => '' !== $inseeCode,
        )));
    }

    /**
     * @param array{name: string, postal_code: ?string} $municipality
     */
    private function municipalityLabel(array $municipality): string
    {
        return null !== $municipality['postal_code'] && '' !== $municipality['postal_code']
            ? "{$municipality['postal_code']} — {$municipality['name']}"
            : $municipality['name'];
    }
}
