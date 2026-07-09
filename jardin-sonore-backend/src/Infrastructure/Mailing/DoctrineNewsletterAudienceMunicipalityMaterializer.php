<?php

declare(strict_types=1);

namespace App\Infrastructure\Mailing;

use App\Application\Mailing\NewsletterAudienceMunicipalityMaterializerInterface;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Model\Mailing\NewsletterAudienceRadiusOrigin;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use InvalidArgumentException;

final readonly class DoctrineNewsletterAudienceMunicipalityMaterializer implements NewsletterAudienceMunicipalityMaterializerInterface
{
    public function __construct(
        private Connection $connection,
        #[Autowire('%app.mailing.home_latitude%')]
        private string $homeLatitude,
        #[Autowire('%app.mailing.home_longitude%')]
        private string $homeLongitude,
    ) {
    }

    public function materialize(NewsletterAudienceFilter $newsletterAudienceFilter): array
    {
        $queryBuilder = $this->connection->createQueryBuilder()
            ->select('DISTINCT municipality.insee_code')
            ->from('municipality', 'municipality')
            ->innerJoin('municipality', 'department', 'department', 'department.id = municipality.department_id')
            ->innerJoin('department', 'region', 'region', 'region.id = department.region_id')
            ->where('municipality.insee_code IS NOT NULL');
        $targets = [];
        $expr = $queryBuilder->expr();

        if ([] !== $newsletterAudienceFilter->getRegionCodes()) {
            $targets[] = $expr->in('region.code', ':regionCodes');
            $queryBuilder->setParameter('regionCodes', $newsletterAudienceFilter->getRegionCodes(), ArrayParameterType::STRING);
        }

        if ([] !== $newsletterAudienceFilter->getDepartmentCodes()) {
            $targets[] = $expr->in('department.code', ':departmentCodes');
            $queryBuilder->setParameter('departmentCodes', $newsletterAudienceFilter->getDepartmentCodes(), ArrayParameterType::STRING);
        }

        if ([] !== $newsletterAudienceFilter->getMunicipalityInseeCodes()) {
            $targets[] = $expr->in('municipality.insee_code', ':municipalityInseeCodes');
            $queryBuilder->setParameter('municipalityInseeCodes', $newsletterAudienceFilter->getMunicipalityInseeCodes(), ArrayParameterType::STRING);
        }

        if (null !== $newsletterAudienceFilter->getRadiusKilometers()) {
            [$originLatitude, $originLongitude] = $this->resolveRadiusOrigin($newsletterAudienceFilter);
            $targets[] = '(
                municipality.center_latitude IS NOT NULL
                AND municipality.center_longitude IS NOT NULL
                AND ST_DISTANCE_SPHERE(
                    POINT(municipality.center_longitude, municipality.center_latitude),
                    POINT(:originLongitude, :originLatitude)
                ) <= :radiusMeters
            )';
            $queryBuilder
                ->setParameter('originLatitude', $originLatitude)
                ->setParameter('originLongitude', $originLongitude)
                ->setParameter('radiusMeters', $newsletterAudienceFilter->getRadiusKilometers() * 1000);
        }

        if ([] === $targets) {
            return [];
        }

        /** @var list<string> $inseeCodes */
        $inseeCodes = $queryBuilder
            ->andWhere('(' . implode("\nOR ", $targets) . ')')
            ->orderBy('municipality.insee_code', 'ASC')
            ->executeQuery()
            ->fetchFirstColumn();

        return $inseeCodes;
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

        $municipality = $this->connection->createQueryBuilder()
            ->select('center_latitude', 'center_longitude')
            ->from('municipality')
            ->where('insee_code = :inseeCode')
            ->setParameter('inseeCode', $newsletterAudienceFilter->getRadiusOriginMunicipalityInseeCode())
            ->executeQuery()
            ->fetchAssociative();

        if (false === $municipality
            || null === $municipality['center_latitude']
            || null === $municipality['center_longitude']) {
            throw new InvalidArgumentException('Newsletter municipality radius origin has no usable coordinates.');
        }

        return [(float) $municipality['center_latitude'], (float) $municipality['center_longitude']];
    }
}
