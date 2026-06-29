<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\Mailing\NewsletterRecommendation;
use App\Domain\Repository\NewsletterRecommendationRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\NewsletterRecommendationEntity;
use App\Infrastructure\Doctrine\Mapper\NewsletterRecommendationMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class NewsletterRecommendationDoctrineRepository implements NewsletterRecommendationRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NewsletterRecommendationMapper $newsletterRecommendationMapper,
    ) {
    }

    public function findByUuid(Uuid $uuid): ?NewsletterRecommendation
    {
        $entity = $this->entityManager->getRepository(NewsletterRecommendationEntity::class)->findOneBy(['uuid' => $uuid]);

        return $entity instanceof NewsletterRecommendationEntity
            ? $this->newsletterRecommendationMapper->toDomain($entity)
            : null;
    }

    public function search(?string $query = null, bool $activeOnly = false): array
    {
        $queryBuilder = $this->entityManager->getRepository(NewsletterRecommendationEntity::class)
            ->createQueryBuilder('recommendation')
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
        $entity = $this->entityManager->getRepository(NewsletterRecommendationEntity::class)
            ->findOneBy(['uuid' => $newsletterRecommendation->getUuid()]);

        $this->entityManager->persist($this->newsletterRecommendationMapper->toEntity(
            newsletterRecommendation: $newsletterRecommendation,
            newsletterRecommendationEntity: $entity instanceof NewsletterRecommendationEntity ? $entity : null,
        ));
        $this->entityManager->flush();
    }
}
