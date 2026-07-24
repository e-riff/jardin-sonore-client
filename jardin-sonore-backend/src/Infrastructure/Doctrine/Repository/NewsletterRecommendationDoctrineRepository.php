<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\Mailing\NewsletterRecommendation;
use App\Domain\Repository\NewsletterRecommendationRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\NewsletterRecommendationEntity;
use App\Infrastructure\Doctrine\Mapper\NewsletterRecommendationMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<NewsletterRecommendationEntity>
 */
final class NewsletterRecommendationDoctrineRepository extends ServiceEntityRepository implements NewsletterRecommendationRepositoryInterface
{
    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly NewsletterRecommendationMapper $newsletterRecommendationMapper,
    ) {
        parent::__construct($managerRegistry, NewsletterRecommendationEntity::class);
    }

    public function findByUuid(Uuid $uuid): ?NewsletterRecommendation
    {
        $entity = $this->findOneBy(['uuid' => $uuid]);

        return $entity instanceof NewsletterRecommendationEntity
            ? $this->newsletterRecommendationMapper->toDomain($entity)
            : null;
    }

    public function search(?string $query = null, bool $activeOnly = false): array
    {
        $queryBuilder = $this->createQueryBuilder('recommendation')
            ->orderBy('recommendation.updatedAt', 'DESC')
            ->addOrderBy('recommendation.id', 'DESC')
            ->setMaxResults(50);

        if (null !== $query && '' !== trim($query)) {
            $queryBuilder
                ->andWhere('LOWER(recommendation.title) LIKE LOWER(:query)')
                ->setParameter('query', '%' . trim($query) . '%');
        }

        if ($activeOnly) {
            $queryBuilder
                ->andWhere('recommendation.active = :active')
                ->setParameter('active', true);
        }

        $recommendations = [];

        foreach ($queryBuilder->getQuery()->getResult() as $entity) {
            $recommendations[] = $this->newsletterRecommendationMapper->toDomain($entity);
        }

        return $recommendations;
    }

    public function save(NewsletterRecommendation $newsletterRecommendation): void
    {
        $entity = $this->findOneBy(['uuid' => $newsletterRecommendation->getUuid()]);

        $this->getEntityManager()->persist($this->newsletterRecommendationMapper->toEntity(
            newsletterRecommendation: $newsletterRecommendation,
            newsletterRecommendationEntity: $entity instanceof NewsletterRecommendationEntity ? $entity : null,
        ));
        $this->getEntityManager()->flush();
    }

    public function delete(NewsletterRecommendation $newsletterRecommendation): void
    {
        $entity = $this->findOneBy(['uuid' => $newsletterRecommendation->getUuid()]);

        if ($entity instanceof NewsletterRecommendationEntity) {
            $this->getEntityManager()->remove($entity);
            $this->getEntityManager()->flush();
        }
    }

    public function findTagSuggestions(): array
    {
        $rows = $this->createQueryBuilder('recommendation')
            ->select('DISTINCT recommendation.tag AS tag')
            ->andWhere('recommendation.tag IS NOT NULL')
            ->andWhere('recommendation.tag != :emptyTag')
            ->setParameter('emptyTag', '')
            ->orderBy('recommendation.tag', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_values(array_map(static fn (array $row): string => $row['tag'], $rows));
    }
}
