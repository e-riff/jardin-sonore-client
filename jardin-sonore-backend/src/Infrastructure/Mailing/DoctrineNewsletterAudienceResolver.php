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
use Doctrine\DBAL\Query\QueryBuilder;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

final readonly class DoctrineNewsletterAudienceResolver implements NewsletterAudienceResolverInterface
{
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

        $recipients = array_values($recipientsByEmailAddress);
        $total = count($recipients);

        if (null !== $limit) {
            $recipients = array_slice($recipients, 0, $limit);
        }

        return new NewsletterAudienceResolution($total, $recipients);
    }

    private function createQueryBuilder(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->select(
                'email.email_address',
                'email.unsubscribe_token',
                "CASE WHEN person.id IS NOT NULL THEN TRIM(CONCAT(person.first_name, ' ', person.last_name)) ELSE organization.name END AS display_name",
            )
            ->from('email_contact', 'email')
            ->innerJoin('email', 'contact_details', 'contact', 'contact.id = email.contact_details_id')
            ->innerJoin('contact', 'directory_entry', 'entry', 'entry.id = contact.directory_entry_id')
            ->leftJoin('entry', 'person', 'person', 'person.id = entry.id')
            ->leftJoin('entry', 'organization', 'organization', 'organization.id = entry.id')
            ->leftJoin('person', 'organization', 'person_organization', 'person_organization.id = person.organization_id')
            ->leftJoin('person_organization', 'directory_entry', 'organization_entry', 'organization_entry.id = person_organization.id')
            ->leftJoin('organization_entry', 'contact_details', 'organization_contact', 'organization_contact.directory_entry_id = organization_entry.id')
            ->where('email.active = 1')
            ->andWhere('email.opt_in_newsletter = 1')
            ->andWhere('email.unsubscribed_at IS NULL')
            ->andWhere("TRIM(email.email_address) <> ''")
            ->andWhere("TRIM(email.unsubscribe_token) <> ''")
            ->andWhere('entry.active = 1')
            ->andWhere('(organization_entry.id IS NULL OR organization_entry.active = 1)')
            ->orderBy('email.email_address', 'ASC');
    }

    private function applyOrganizationFilters(
        QueryBuilder $queryBuilder,
        NewsletterAudienceFilter $newsletterAudienceFilter,
    ): void {
        $organizationTypes = $this->enumValues($newsletterAudienceFilter->getOrganizationTypes());

        if ([] !== $organizationTypes) {
            $queryBuilder
                ->andWhere('(organization.type IN (:organizationTypes) OR person_organization.type IN (:organizationTypes))')
                ->setParameter('organizationTypes', $organizationTypes, ArrayParameterType::STRING);
        }

        $organizationSectors = $this->enumValues($newsletterAudienceFilter->getOrganizationSectors());

        if ([] !== $organizationSectors) {
            $queryBuilder
                ->andWhere('(organization.sector IN (:organizationSectors) OR person_organization.sector IN (:organizationSectors))')
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
            ->andWhere('COALESCE(organization_entry.customer_status, entry.customer_status) IN (:customerStatuses)')
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
                'EXISTS (
                    SELECT 1
                    FROM directory_entry_tag entry_tag
                    INNER JOIN tag ON tag.id = entry_tag.tag_id
                    WHERE entry_tag.directory_entry_id IN (entry.id, organization_entry.id)
                    AND tag.uuid IN (:tagUuids)
                )',
            )
            ->setParameter('tagUuids', $tagUuids, ArrayParameterType::BINARY);
    }

    private function applyGeographicFilters(
        QueryBuilder $queryBuilder,
        NewsletterAudienceFilter $newsletterAudienceFilter,
    ): void {
        $geographicTargets = [];

        if ([] !== $newsletterAudienceFilter->getRegionCodes()) {
            $geographicTargets[] = 'region.code IN (:regionCodes)';
            $queryBuilder->setParameter(
                'regionCodes',
                $newsletterAudienceFilter->getRegionCodes(),
                ArrayParameterType::STRING,
            );
        }

        if ([] !== $newsletterAudienceFilter->getDepartmentCodes()) {
            $geographicTargets[] = 'department.code IN (:departmentCodes)';
            $queryBuilder->setParameter(
                'departmentCodes',
                $newsletterAudienceFilter->getDepartmentCodes(),
                ArrayParameterType::STRING,
            );
        }

        if ([] !== $newsletterAudienceFilter->getMunicipalityInseeCodes()) {
            $geographicTargets[] = 'municipality.insee_code IN (:municipalityInseeCodes)';
            $queryBuilder->setParameter(
                'municipalityInseeCodes',
                $newsletterAudienceFilter->getMunicipalityInseeCodes(),
                ArrayParameterType::STRING,
            );
        }

        if (null !== $newsletterAudienceFilter->getRadiusKilometers()) {
            [$originLatitude, $originLongitude] = $this->resolveRadiusOrigin($newsletterAudienceFilter);
            $geographicTargets[] = '(
                municipality.center_latitude IS NOT NULL
                AND municipality.center_longitude IS NOT NULL
                AND
                ST_DISTANCE_SPHERE(
                    POINT(municipality.center_longitude, municipality.center_latitude),
                    POINT(:originLongitude, :originLatitude)
                ) <= :radiusMeters
            )';
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
                AND (address.contact_details_id = contact.id OR address.contact_details_id = organization_contact.id)
                AND (%s)
            )',
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
        $municipality = $this->connection->fetchAssociative(
            'SELECT center_latitude, center_longitude
            FROM municipality
            WHERE insee_code = :inseeCode',
            ['inseeCode' => $municipalityInseeCode],
        );

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
}
