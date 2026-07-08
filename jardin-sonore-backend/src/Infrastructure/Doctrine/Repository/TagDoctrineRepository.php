<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\AddressBook\Tag;
use App\Domain\Repository\TagRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\TagEntity;
use App\Infrastructure\Doctrine\Mapper\TagMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<TagEntity>
 */
final class TagDoctrineRepository extends ServiceEntityRepository implements TagRepositoryInterface
{
    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly TagMapper $tagMapper,
    ) {
        parent::__construct($managerRegistry, TagEntity::class);
    }

    public function findByUuid(Uuid $uuid): ?Tag
    {
        $tagEntity = $this->findOneBy(['uuid' => $uuid]);

        return $tagEntity instanceof TagEntity ? $this->tagMapper->toDomain($tagEntity) : null;
    }

    public function findByLabel(string $label): ?Tag
    {
        $tagEntity = $this->findOneBy(['label' => $label]);

        return $tagEntity instanceof TagEntity ? $this->tagMapper->toDomain($tagEntity) : null;
    }

    public function save(Tag $tag): void
    {
        $tagEntity = $this->findOneBy(['uuid' => $tag->getUuid()]);

        $this->getEntityManager()->persist($this->tagMapper->toEntity(
            tag: $tag,
            tagEntity: $tagEntity instanceof TagEntity ? $tagEntity : null,
        ));
        $this->getEntityManager()->flush();
    }
}
