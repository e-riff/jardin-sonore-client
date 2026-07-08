<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailing;

use App\Application\Mailing\NewsletterAudienceResolution;
use App\Application\Mailing\NewsletterAudienceResolverInterface;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Model\Mailing\NewsletterAudienceRadiusOrigin;
use App\Domain\Model\Mailing\NewsletterRecipient;
use App\Domain\Model\ValueObject\EmailAddress;
use BackedEnum;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\QueryBuilder;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineNewsletterAudienceResolver implements NewsletterAudienceResolverInterface
{
    private const string EMAIL_ALIAS = 'email';
    private const string EMAIL_LINK_ALIAS = 'email_link';
    private const string CONTACT_ALIAS = 'contact';
    private const string ENTRY_ALIAS = 'entry';
    private const string PERSON_ALIAS = 'person';
    private const string ORGANIZATION_ALIAS = 'organization';
    private const string PERSON_ORGANIZATION_ALIAS = 'person_organization';
    private const string ORGANIZATION_ENTRY_ALIAS = 'organization_entry';
    private const string ORGANIZATION_CONTACT_ALIAS = 'organization_contact';

    public function __construct(
        private Connection $connection,
        #[Autowire('%env(default:app.mailing.home_latitude:MAILING_HOME_LATITUDE)%')]
        private string $homeLatitude,
        #[Autowire('%env(default:app.mailing.home_longitude:MAILING_HOME_LONGITUDE)%')]
        private string $homeLongitude,
    ) {
    }

    public function resolve(
        NewsletterAudienceFilter $newsletterAudienceFilter,
        ?int $limit = null,
    ): NewsletterAudienceResolution {
        if (null !== $limit && 1 > $limit) {
            throw new InvalidArgumentException('Newsletter audience resolution limit must be greater than zero.');
        }

        $queryBuilder = $this->createQueryBuilder();
        $this->applyOrganizationFilters($queryBuilder, $newsletterAudienceFilter);
        $this->applyCustomerStatusFilter($queryBuilder, $newsletterAudienceFilter);
        $this->applyTagFilter($queryBuilder, $newsletterAudienceFilter);
        $this->applyGeographicFilters($queryBuilder, $newsletterAudienceFilter);

        $recipientsByEmailAddress = $this->mapRecipientsByEmailAddress($queryBuilder);
        $recipients = array_values($recipientsByEmailAddress);
        $total = count($recipients);

        if (null !== $limit) {
            $recipients = array_slice($recipients, 0, $limit);
        }

        return new NewsletterAudienceResolution($total, $recipients);
    }

    private function createQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connection->createQueryBuilder();
        $expr = $queryBuilder->expr();

        return $queryBuilder
            ->select(
                self::EMAIL_ALIAS . '.email_address',
                self::EMAIL_ALIAS . '.unsubscribe_token',
                sprintf(
                    'CASE WHEN %s.id IS NOT NULL THEN TRIM(CONCAT(%s.first_name, \' \', %s.last_name)) ELSE %s.name END AS display_name',
                    self::PERSON_ALIAS,
                    self::PERSON_ALIAS,
                    self::PERSON_ALIAS,
                    self::ORGANIZATION_ALIAS,
                ),
            )
            ->from('email_contact', self::EMAIL_ALIAS)
            ->innerJoin(self::EMAIL_ALIAS, 'contact_details_email_link', self::EMAIL_LINK_ALIAS, self::EMAIL_LINK_ALIAS . '.email_contact_id = ' . self::EMAIL_ALIAS . '.id')
            ->innerJoin(self::EMAIL_LINK_ALIAS, 'contact_details', self::CONTACT_ALIAS, self::CONTACT_ALIAS . '.id = ' . self::EMAIL_LINK_ALIAS . '.contact_details_id')
            ->innerJoin(self::CONTACT_ALIAS, 'directory_entry', self::ENTRY_ALIAS, self::ENTRY_ALIAS . '.id = ' . self::CONTACT_ALIAS . '.directory_entry_id')
            ->leftJoin(self::ENTRY_ALIAS, 'person', self::PERSON_ALIAS, self::PERSON_ALIAS . '.id = ' . self::ENTRY_ALIAS . '.id')
            ->leftJoin(self::ENTRY_ALIAS, 'organization', self::ORGANIZATION_ALIAS, self::ORGANIZATION_ALIAS . '.id = ' . self::ENTRY_ALIAS . '.id')
            ->leftJoin(self::PERSON_ALIAS, 'organization', self::PERSON_ORGANIZATION_ALIAS, self::PERSON_ORGANIZATION_ALIAS . '.id = ' . self::PERSON_ALIAS . '.organization_id')
            ->leftJoin(self::PERSON_ORGANIZATION_ALIAS, 'directory_entry', self::ORGANIZATION_ENTRY_ALIAS, self::ORGANIZATION_ENTRY_ALIAS . '.id = ' . self::PERSON_ORGANIZATION_ALIAS . '.id')
            ->leftJoin(self::ORGANIZATION_ENTRY_ALIAS, 'contact_details', self::ORGANIZATION_CONTACT_ALIAS, self::ORGANIZATION_CONTACT_ALIAS . '.directory_entry_id = ' . self::ORGANIZATION_ENTRY_ALIAS . '.id')
            ->where($expr->eq(self::EMAIL_ALIAS . '.active', '1'))
            ->andWhere($expr->eq(self::EMAIL_LINK_ALIAS . '.active', '1'))
            ->andWhere($expr->eq(self::EMAIL_ALIAS . '.opt_in_newsletter', '1'))
            ->andWhere($expr->isNull(self::EMAIL_ALIAS . '.unsubscribed_at'))
            ->andWhere('TRIM(' . self::EMAIL_ALIAS . ".email_address) <> ''")
            ->andWhere('TRIM(' . self::EMAIL_ALIAS . ".unsubscribe_token) <> ''")
            ->andWhere($expr->eq(self::ENTRY_ALIAS . '.active', '1'))
            ->andWhere($expr->or(
                $expr->isNull(self::ORGANIZATION_ENTRY_ALIAS . '.id'),
                $expr->eq(self::ORGANIZATION_ENTRY_ALIAS . '.active', '1'),
            ))
            ->orderBy(self::EMAIL_ALIAS . '.email_address', 'ASC');
    }

    private function applyOrganizationFilters(
        QueryBuilder $queryBuilder,
        NewsletterAudienceFilter $newsletterAudienceFilter,
    ): void {
        $organizationTypes = $this->enumValues($newsletterAudienceFilter->getOrganizationTypes());

        if ([] !== $organizationTypes) {
            $queryBuilder
                ->andWhere($this->organizationFieldMatchesExpression(
                    $queryBuilder,
                    'type',
                    ':organizationTypes',
                ))
                ->setParameter('organizationTypes', $organizationTypes, ArrayParameterType::STRING);
        }

        $organizationSectors = $this->enumValues($newsletterAudienceFilter->getOrganizationSectors());

        if ([] !== $organizationSectors) {
            $queryBuilder
                ->andWhere($this->organizationFieldMatchesExpression(
                    $queryBuilder,
                    'sector',
                    ':organizationSectors',
                ))
                ->setParameter('organizationSectors', $organizationSectors, ArrayParameterType::STRING);
        }
    }

    private function applyCustomerStatusFilter(
        QueryBuilder $queryBuilder,
        NewsletterAudienceFilter $newsletterAudienceFilter,
    ): void {
        $customerStatuses = $this->enumValues($newsletterAudienceFilter->getCustomerStatuses());

        if ([] === $customerStatuses) {
            return;
        }

        $queryBuilder
            ->andWhere(
                sprintf(
                    'COALESCE(%s.customer_status, %s.customer_status) IN (:customerStatuses)',
                    self::ORGANIZATION_ENTRY_ALIAS,
                    self::ENTRY_ALIAS,
                ),
            )
            ->setParameter('customerStatuses', $customerStatuses, ArrayParameterType::STRING);
    }

    private function applyTagFilter(
        QueryBuilder $queryBuilder,
        NewsletterAudienceFilter $newsletterAudienceFilter,
    ): void {
        $tagUuids = array_map(
            static fn (string $tagUuid): string => Uuid::fromString($tagUuid)->toBinary(),
            $newsletterAudienceFilter->getTagUuids(),
        );

        if ([] === $tagUuids) {
            return;
        }

        $queryBuilder
            ->andWhere(
                sprintf(
                    'EXISTS (
                    SELECT 1
                    FROM directory_entry_tag entry_tag
                    INNER JOIN tag ON tag.id = entry_tag.tag_id
                    WHERE entry_tag.directory_entry_id IN (%s.id, %s.id)
                    AND tag.uuid IN (:tagUuids)
                )',
                    self::ENTRY_ALIAS,
                    self::ORGANIZATION_ENTRY_ALIAS,
                ),
            )
            ->setParameter('tagUuids', $tagUuids, ArrayParameterType::BINARY);
    }

    private function applyGeographicFilters(
        QueryBuilder $queryBuilder,
        NewsletterAudienceFilter $newsletterAudienceFilter,
    ): void {
        $geographicTargets = [];
        $expr = $queryBuilder->expr();

        if ([] !== $newsletterAudienceFilter->getRegionCodes()) {
            $geographicTargets[] = $expr->in('region.code', ':regionCodes');
            $queryBuilder->setParameter(
                'regionCodes',
                $newsletterAudienceFilter->getRegionCodes(),
                ArrayParameterType::STRING,
            );
        }

        if ([] !== $newsletterAudienceFilter->getDepartmentCodes()) {
            $geographicTargets[] = $expr->in('department.code', ':departmentCodes');
            $queryBuilder->setParameter(
                'departmentCodes',
                $newsletterAudienceFilter->getDepartmentCodes(),
                ArrayParameterType::STRING,
            );
        }

        if ([] !== $newsletterAudienceFilter->getMunicipalityInseeCodes()) {
            $geographicTargets[] = $expr->in('municipality.insee_code', ':municipalityInseeCodes');
            $queryBuilder->setParameter(
                'municipalityInseeCodes',
                $newsletterAudienceFilter->getMunicipalityInseeCodes(),
                ArrayParameterType::STRING,
            );
        }

        if (null !== $newsletterAudienceFilter->getRadiusKilometers()) {
            [$originLatitude, $originLongitude] = $this->resolveRadiusOrigin($newsletterAudienceFilter);
            $geographicTargets[] = $this->radiusTargetExpression();
            $queryBuilder
                ->setParameter('originLatitude', $originLatitude)
                ->setParameter('originLongitude', $originLongitude)
                ->setParameter('radiusMeters', $newsletterAudienceFilter->getRadiusKilometers() * 1000);
        }

        if ([] === $geographicTargets) {
            return;
        }

        $queryBuilder->andWhere(sprintf(
            'EXISTS (
                SELECT 1
                FROM address_contact address
                INNER JOIN municipality ON municipality.id = address.municipality_id
                INNER JOIN department ON department.id = municipality.department_id
                INNER JOIN region ON region.id = department.region_id
                WHERE address.active = 1
                AND (address.contact_details_id = %s.id OR address.contact_details_id = %s.id)
                AND (%s)
            )',
            self::CONTACT_ALIAS,
            self::ORGANIZATION_CONTACT_ALIAS,
            implode("\nOR ", $geographicTargets),
        ));
    }

    /**
     * @return array{float, float}
     */
    private function resolveRadiusOrigin(NewsletterAudienceFilter $newsletterAudienceFilter): array
    {
        if (NewsletterAudienceRadiusOrigin::HOME === $newsletterAudienceFilter->getRadiusOrigin()) {
            if (!is_numeric($this->homeLatitude) || !is_numeric($this->homeLongitude)) {
                throw new InvalidArgumentException('Newsletter home radius origin coordinates are not configured.');
            }

            return [(float) $this->homeLatitude, (float) $this->homeLongitude];
        }

        if (NewsletterAudienceRadiusOrigin::CUSTOM === $newsletterAudienceFilter->getRadiusOrigin()) {
            $latitude = $newsletterAudienceFilter->getRadiusOriginCustomLatitude();
            $longitude = $newsletterAudienceFilter->getRadiusOriginCustomLongitude();

            if (null === $latitude || null === $longitude) {
                throw new InvalidArgumentException('Newsletter custom radius origin coordinates are missing.');
            }

            return [$latitude, $longitude];
        }

        $municipalityInseeCode = $newsletterAudienceFilter->getRadiusOriginMunicipalityInseeCode();
        $municipality = $this->connection->createQueryBuilder()
            ->select('center_latitude', 'center_longitude')
            ->from('municipality')
            ->where('insee_code = :inseeCode')
            ->setParameter('inseeCode', $municipalityInseeCode)
            ->executeQuery()
            ->fetchAssociative();

        if (false === $municipality
            || null === $municipality['center_latitude']
            || null === $municipality['center_longitude']) {
            throw new InvalidArgumentException('Newsletter municipality radius origin has no usable coordinates.');
        }

        return [(float) $municipality['center_latitude'], (float) $municipality['center_longitude']];
    }

    /**
     * @param list<BackedEnum> $enums
     *
     * @return list<int|string>
     */
    private function enumValues(array $enums): array
    {
        return array_map(static fn (BackedEnum $enum): int|string => $enum->value, $enums);
    }

    private function normalizeDisplayName(?string $displayName): ?string
    {
        $displayName = null === $displayName ? null : trim($displayName);

        return null === $displayName || '' === $displayName ? null : $displayName;
    }

    /**
     * @return array<string, NewsletterRecipient>
     */
    private function mapRecipientsByEmailAddress(QueryBuilder $queryBuilder): array
    {
        /** @var list<array{email_address: string, unsubscribe_token: string, display_name: ?string}> $rows */
        $rows = $queryBuilder->executeQuery()->fetchAllAssociative();
        $recipientsByEmailAddress = [];

        foreach ($rows as $row) {
            $emailAddress = mb_strtolower(trim($row['email_address']));

            try {
                $newsletterRecipient = new NewsletterRecipient(
                    emailAddress: new EmailAddress($emailAddress),
                    unsubscribeToken: $row['unsubscribe_token'],
                    displayName: $this->normalizeDisplayName($row['display_name']),
                );
            } catch (InvalidArgumentException) {
                continue;
            }

            $recipientsByEmailAddress[$emailAddress] = $newsletterRecipient;
        }

        return $recipientsByEmailAddress;
    }

    private function organizationFieldMatchesExpression(
        QueryBuilder $queryBuilder,
        string $field,
        string $parameter,
    ): CompositeExpression {
        $expr = $queryBuilder->expr();

        return $expr->or(
            $expr->in(self::ORGANIZATION_ALIAS . ".{$field}", $parameter),
            $expr->in(self::PERSON_ORGANIZATION_ALIAS . ".{$field}", $parameter),
        );
    }

    private function radiusTargetExpression(): string
    {
        return '(
            municipality.center_latitude IS NOT NULL
            AND municipality.center_longitude IS NOT NULL
            AND ST_DISTANCE_SPHERE(
                POINT(municipality.center_longitude, municipality.center_latitude),
                POINT(:originLongitude, :originLatitude)
            ) <= :radiusMeters
        )';
    }
}
