<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailing;

use App\Application\Mailing\NewsletterAudienceOptionsQueryInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineNewsletterAudienceOptionsQuery implements NewsletterAudienceOptionsQueryInterface
{
    /**
     * @var list<string>
     */
    private const array PREFERRED_DEPARTMENT_CODES = ['42', '69', '43', '07'];
    private const string PREFERRED_DEPARTMENT_GROUP = 'mailing.audience.form.department_group_preferred';
    private const string ALL_DEPARTMENT_GROUP = 'mailing.audience.form.department_group_all';

    public function __construct(private Connection $connection)
    {
    }

    public function getTagChoices(): array
    {
        /** @var list<array{label: string, uuid: string}> $tags */
        $tags = $this->connection->createQueryBuilder()
            ->select('label', 'uuid')
            ->from('tag')
            ->orderBy('label', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        $choices = [];

        foreach ($tags as $tag) {
            $choices[$tag['label']] = Uuid::fromBinary($tag['uuid'])->toRfc4122();
        }

        return $choices;
    }

    public function getRegionChoices(): array
    {
        return $this->connection->createQueryBuilder()
            ->select("CONCAT(code, ' — ', name)", 'code')
            ->from('region')
            ->orderBy('code', 'ASC')
            ->executeQuery()
            ->fetchAllKeyValue();
    }

    public function getDepartmentChoices(): array
    {
        /** @var list<array{code: string, name: string}> $departments */
        $departments = $this->preferredDepartmentOrderQueryBuilder('department')
            ->select('code', 'name')
            ->from('department')
            ->executeQuery()
            ->fetchAllAssociative();

        $choices = [
            self::PREFERRED_DEPARTMENT_GROUP => [],
            self::ALL_DEPARTMENT_GROUP => [],
        ];

        foreach ($departments as $department) {
            $group = in_array($department['code'], self::PREFERRED_DEPARTMENT_CODES, true)
                ? self::PREFERRED_DEPARTMENT_GROUP
                : self::ALL_DEPARTMENT_GROUP;

            $choices[$group]["{$department['code']} — {$department['name']}"] = $department['code'];
        }

        return $choices;
    }

    public function getMunicipalityChoices(): array
    {
        $municipalities = $this->fetchMunicipalitiesForChoices();

        $choices = [];

        foreach ($municipalities as $municipality) {
            $choices[$this->departmentLabel($municipality)][$this->municipalityLabel($municipality)] = $municipality['insee_code'];
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
        $existingInseeCodes = $this->connection->createQueryBuilder()
            ->select('municipality.insee_code')
            ->from('municipality', 'municipality')
            ->where('municipality.insee_code IN (:inseeCodes)')
            ->setParameter('inseeCodes', $inseeCodes, ArrayParameterType::STRING)
            ->executeQuery()
            ->fetchFirstColumn();
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
        $municipalities = $this->connection->createQueryBuilder()
            ->select('municipality.insee_code', 'municipality.name', 'municipality.postal_code')
            ->from('municipality', 'municipality')
            ->where('municipality.insee_code IN (:inseeCodes)')
            ->setParameter('inseeCodes', $inseeCodes, ArrayParameterType::STRING)
            ->executeQuery()
            ->fetchAllAssociative();
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
        $municipalities = $this->searchMunicipalities($query, $likeQuery, $offset, $limit + 1);

        $hasNextPage = count($municipalities) > $limit;
        $municipalities = array_slice($municipalities, 0, $limit);
        $optgroups = [];
        $results = [];

        foreach ($municipalities as $municipality) {
            $departmentLabel = $this->departmentLabel($municipality);
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

    private function preferredDepartmentOrderQueryBuilder(string $departmentAlias): QueryBuilder
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $queryBuilder->addOrderBy(
            sprintf(
                "CASE %s.code
                    WHEN '42' THEN 0
                    WHEN '69' THEN 1
                    WHEN '43' THEN 2
                    WHEN '07' THEN 3
                    ELSE 4
                END",
                $departmentAlias,
            ),
            'ASC',
        );
        $queryBuilder->addOrderBy("{$departmentAlias}.code", 'ASC');

        return $queryBuilder;
    }

    /**
     * @return list<array{
     *     insee_code: string,
     *     name: string,
     *     postal_code: ?string,
     *     department_code: string,
     *     department_name: string
     * }>
     */
    private function fetchMunicipalitiesForChoices(): array
    {
        return $this->baseMunicipalityQueryBuilder()
            ->distinct()
            ->where('municipality.insee_code IS NOT NULL')
            ->addOrderBy('municipality.name', 'ASC')
            ->addOrderBy('municipality.postal_code', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @return list<array{
     *     insee_code: string,
     *     name: string,
     *     postal_code: ?string,
     *     department_code: string,
     *     department_name: string
     * }>
     */
    private function searchMunicipalities(string $query, string $likeQuery, int $offset, int $limit): array
    {
        $queryBuilder = $this->baseMunicipalityQueryBuilder()
            ->where('municipality.insee_code IS NOT NULL')
            ->addOrderBy('municipality.name', 'ASC')
            ->addOrderBy('municipality.postal_code', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->setParameter('likeQuery', $likeQuery);

        if ('' !== $query) {
            $expr = $queryBuilder->expr();
            $queryBuilder->andWhere($expr->or(
                $expr->like('municipality.name', ':likeQuery'),
                $expr->like('municipality.postal_code', ':likeQuery'),
                $expr->like('municipality.insee_code', ':likeQuery'),
                $expr->like('department.code', ':likeQuery'),
            ));
        }

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    private function baseMunicipalityQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->preferredDepartmentOrderQueryBuilder('department');

        return $queryBuilder
            ->select(
                'municipality.insee_code',
                'municipality.name',
                'municipality.postal_code',
                'department.code AS department_code',
                'department.name AS department_name',
            )
            ->from('municipality', 'municipality')
            ->innerJoin('municipality', 'department', 'department', 'department.id = municipality.department_id');
    }

    /**
     * @param array{department_code: string, department_name: string} $municipality
     */
    private function departmentLabel(array $municipality): string
    {
        return "{$municipality['department_code']} — {$municipality['department_name']}";
    }
}
