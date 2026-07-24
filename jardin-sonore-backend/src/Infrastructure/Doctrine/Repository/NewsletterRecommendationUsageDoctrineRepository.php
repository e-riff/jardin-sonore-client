<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\Mailing\NewsletterRecommendationUsage;
use App\Domain\Repository\NewsletterRecommendationUsageRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\NewsletterRecommendationUsageEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<NewsletterRecommendationUsageEntity> */
final class NewsletterRecommendationUsageDoctrineRepository extends ServiceEntityRepository implements NewsletterRecommendationUsageRepositoryInterface
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, NewsletterRecommendationUsageEntity::class);
    }

    public function save(NewsletterRecommendationUsage $newsletterRecommendationUsage): void
    {
        $entity = $this->findOneBy([
            'sourceRecommendationUuid' => $newsletterRecommendationUsage->sourceRecommendationUuid,
            'campaignUuid' => $newsletterRecommendationUsage->campaignUuid,
        ]);

        if ($entity instanceof NewsletterRecommendationUsageEntity) {
            return;
        }

        $entity = (new NewsletterRecommendationUsageEntity())
            ->setSourceRecommendationUuid($newsletterRecommendationUsage->sourceRecommendationUuid)
            ->setCampaignUuid($newsletterRecommendationUsage->campaignUuid)
            ->setCampaignTitle($newsletterRecommendationUsage->campaignTitle)
            ->setSentAt($newsletterRecommendationUsage->sentAt);

        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }

    public function findBySourceRecommendationUuids(array $sourceRecommendationUuids): array
    {
        if ([] === $sourceRecommendationUuids) {
            return [];
        }

        $entities = $this->createQueryBuilder('usage')
            ->andWhere('usage.sourceRecommendationUuid IN (:sourceRecommendationUuids)')
            ->setParameter('sourceRecommendationUuids', $sourceRecommendationUuids)
            ->orderBy('usage.sentAt', 'DESC')
            ->getQuery()
            ->getResult();

        $usagesBySourceRecommendationUuid = [];

        foreach ($entities as $entity) {
            if (!$entity instanceof NewsletterRecommendationUsageEntity) {
                continue;
            }

            $sourceRecommendationUuid = $entity->getSourceRecommendationUuid()->toRfc4122();
            $usagesBySourceRecommendationUuid[$sourceRecommendationUuid][] = new NewsletterRecommendationUsage(
                sourceRecommendationUuid: $entity->getSourceRecommendationUuid(),
                campaignUuid: $entity->getCampaignUuid(),
                campaignTitle: $entity->getCampaignTitle(),
                sentAt: $entity->getSentAt(),
            );
        }

        return $usagesBySourceRecommendationUuid;
    }
}
