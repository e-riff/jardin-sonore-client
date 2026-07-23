<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\Session\MediaResource;
use App\Domain\Model\Session\MediaResourceType;
use App\Domain\Repository\MediaResourceRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\MediaResourceEntity;
use App\Infrastructure\Doctrine\Entity\ThemeEntity;
use App\Infrastructure\Doctrine\Mapper\MediaResourceMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<MediaResourceEntity>
 */
final class MediaResourceDoctrineRepository extends ServiceEntityRepository implements MediaResourceRepositoryInterface
{
    public function __construct(ManagerRegistry $managerRegistry, private readonly MediaResourceMapper $mediaResourceMapper)
    {
        parent::__construct($managerRegistry, MediaResourceEntity::class);
    }

    public function findByUuid(Uuid $uuid): ?MediaResource
    {
        $entity = $this->findOneBy(['uuid' => $uuid]);

        return $entity instanceof MediaResourceEntity ? $this->mediaResourceMapper->toDomain($entity) : null;
    }

    public function search(?string $query = null, ?MediaResourceType $mediaResourceType = null, bool $activeOnly = false): array
    {
        $qb = $this->createQueryBuilder('media')
            ->orderBy('media.updatedAt', 'DESC')
            ->addOrderBy('media.id', 'DESC');
        if (null !== $mediaResourceType) {
            $qb->andWhere('media.type = :type')->setParameter('type', $mediaResourceType);
        }
        if (null !== $query && '' !== trim($query)) {
            $qb
                ->andWhere('
                    LOWER(media.title) LIKE LOWER(:query)
                    OR LOWER(COALESCE(media.source, \'\')) LIKE LOWER(:query)
                    OR LOWER(COALESCE(media.description, \'\')) LIKE LOWER(:query)
                    OR LOWER(media.primaryUrl) LIKE LOWER(:query)
                    OR LOWER(COALESCE(media.secondaryUrl, \'\')) LIKE LOWER(:query)
                ')
                ->setParameter('query', '%' . trim($query) . '%');
        }
        if ($activeOnly) {
            $qb->andWhere('media.active = :active')->setParameter('active', true);
        }

        return array_map(fn ($entity): MediaResource => $this->mediaResourceMapper->toDomain($entity), $qb->getQuery()->getResult());
    }

    public function save(MediaResource $mediaResource): void
    {
        $entity = $this->findOneBy(['uuid' => $mediaResource->getUuid()]);
        $mediaResourceEntity = $this->mediaResourceMapper->toEntity($mediaResource, $entity instanceof MediaResourceEntity ? $entity : null);
        $this->syncThemes($mediaResourceEntity, $mediaResource->getThemes());
        $this->getEntityManager()->persist($mediaResourceEntity);
        $this->getEntityManager()->flush();
    }

    /** @param list<\App\Domain\Model\ContentCatalog\Theme> $themes */
    private function syncThemes(MediaResourceEntity $mediaResourceEntity, array $themes): void
    {
        $wantedUuids = array_map(static fn ($theme): string => $theme->getUuid()->toRfc4122(), $themes);
        foreach ($mediaResourceEntity->getThemes()->toArray() as $themeEntity) {
            if (!in_array($themeEntity->getUuid()->toRfc4122(), $wantedUuids, true)) {
                $mediaResourceEntity->removeTheme($themeEntity);
            }
        }
        foreach ($wantedUuids as $uuid) {
            $themeEntity = $this->getEntityManager()->getRepository(ThemeEntity::class)->findOneBy(['uuid' => $uuid]);
            if ($themeEntity instanceof ThemeEntity) {
                $mediaResourceEntity->addTheme($themeEntity);
            }
        }
    }

    public function delete(MediaResource $mediaResource): void
    {
        $entity = $this->findOneBy(['uuid' => $mediaResource->getUuid()]);

        if (!$entity instanceof MediaResourceEntity) {
            return;
        }

        $this->getEntityManager()->remove($entity);
        $this->getEntityManager()->flush();
    }
}
