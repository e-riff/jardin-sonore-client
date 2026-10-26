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

    public function findByLabel(string $label): ?Theme
    {
        $entity = $this->findOneBy(['label' => trim($label)]);

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

    public function usageCountsByUuid(): array
    {
        $rows = $this->getEntityManager()->getConnection()->createQueryBuilder()
            ->select(
                'LOWER(HEX(theme.uuid)) AS uuid',
                '(SELECT COUNT(*) FROM session_summary_theme WHERE theme_id = theme.id) + '
                    . '(SELECT COUNT(*) FROM repertoire_item_theme WHERE theme_id = theme.id) + '
                    . '(SELECT COUNT(*) FROM media_resource_theme WHERE theme_id = theme.id) AS usage_count',
            )
            ->from('theme', 'theme')
            ->executeQuery()
            ->fetchAllAssociative();
        $usageCounts = [];

        foreach ($rows as $row) {
            $usageCounts[Uuid::fromString($this->uuidFromHex((string) $row['uuid']))->toRfc4122()] = (int) $row['usage_count'];
        }

        return $usageCounts;
    }

    public function delete(Theme $theme): void
    {
        $entity = $this->findOneBy(['uuid' => $theme->getUuid()]);
        if (!$entity instanceof ThemeEntity) {
            return;
        }

        $this->getEntityManager()->remove($entity);
        $this->getEntityManager()->flush();
    }

    private function uuidFromHex(string $uuidHex): string
    {
        return substr($uuidHex, 0, 8) . '-' . substr($uuidHex, 8, 4) . '-' . substr($uuidHex, 12, 4) . '-' . substr($uuidHex, 16, 4) . '-' . substr($uuidHex, 20);
    }
}
