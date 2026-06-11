<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\AddressBook\Tag;
use App\Domain\Repository\TagRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\TagEntity;
use App\Infrastructure\Doctrine\Mapper\TagMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class TagDoctrineRepository implements TagRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TagMapper $tagMapper,
    ) {
    }

    public function findByUuid(Uuid $uuid): ?Tag
    {
        $tagEntity = $this->entityManager->getRepository(TagEntity::class)->findOneBy(['uuid' => $uuid]);

        return $tagEntity instanceof TagEntity ? $this->tagMapper->toDomain($tagEntity) : null;
    }

    public function findByLabel(string $label): ?Tag
    {
        $tagEntity = $this->entityManager->getRepository(TagEntity::class)->findOneBy(['label' => $label]);

        return $tagEntity instanceof TagEntity ? $this->tagMapper->toDomain($tagEntity) : null;
    }

    public function save(Tag $tag): void
    {
        $tagEntity = $this->entityManager->getRepository(TagEntity::class)->findOneBy(['uuid' => $tag->getUuid()]);

        $this->entityManager->persist($this->tagMapper->toEntity(
            tag: $tag,
            tagEntity: $tagEntity instanceof TagEntity ? $tagEntity : null,
        ));
        $this->entityManager->flush();
    }
}
