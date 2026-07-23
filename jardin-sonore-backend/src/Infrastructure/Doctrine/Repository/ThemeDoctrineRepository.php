<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\ContentCatalog\Theme;
use App\Domain\Repository\ThemeRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\ThemeEntity;
use App\Infrastructure\Doctrine\Mapper\ThemeMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<ThemeEntity> */
final class ThemeDoctrineRepository extends ServiceEntityRepository implements ThemeRepositoryInterface
{
    public function __construct(ManagerRegistry $managerRegistry, private readonly ThemeMapper $themeMapper)
    {
        parent::__construct($managerRegistry, ThemeEntity::class);
    }

    public function findByUuid(Uuid $uuid): ?Theme
    {
        $entity = $this->findOneBy(['uuid' => $uuid]);

        return $entity instanceof ThemeEntity ? $this->themeMapper->toDomain($entity) : null;
    }

    public function findAllOrderedByLabel(): array
    {
        return array_map(fn (ThemeEntity $entity): Theme => $this->themeMapper->toDomain($entity), $this->findBy([], ['label' => 'ASC']));
    }

    public function save(Theme $theme): void
    {
        $entity = $this->findOneBy(['uuid' => $theme->getUuid()]);
        $this->getEntityManager()->persist($this->themeMapper->toEntity($theme, $entity instanceof ThemeEntity ? $entity : null));
        $this->getEntityManager()->flush();
    }
}
