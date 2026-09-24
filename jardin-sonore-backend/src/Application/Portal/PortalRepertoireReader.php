<?php

declare(strict_types=1);

namespace App\Application\Portal;

use App\Infrastructure\Doctrine\Entity\MediaResourceEntity;
use App\Infrastructure\Doctrine\Entity\RepertoireItemEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Repository\RepertoireItemDoctrineRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class PortalRepertoireReader
{
    public function __construct(
        private PortalSessionReader $portalSessionReader,
        private RepertoireItemDoctrineRepository $repertoireItemDoctrineRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array{items: list<array<string, mixed>>, total: int, page: int, pageSize: int} */
    public function paginated(UserEntity $userEntity, PortalListCriteria $criteria): array
    {
        $organizationsBySourceUuid = $this->portalSessionReader->authorizedRepertoireOrganizations($userEntity, $criteria->organizationUuid);
        $page = $this->repertoireItemDoctrineRepository->paginatedPortalItems(array_keys($organizationsBySourceUuid), $criteria);
        $items = array_map(fn (RepertoireItemEntity $itemEntity): array => PortalRepertoireResponse::fromEntity(
            $itemEntity,
            $organizationsBySourceUuid[$itemEntity->getUuid()->toRfc4122()],
            $this->mediaEntities($itemEntity),
        ), $page['items']);

        return ['items' => $items, 'total' => $page['total'], 'page' => $page['page'], 'pageSize' => $page['pageSize']];
    }

    /** @return list<array{uuid: string, label: string, color: string}> */
    public function availableThemes(UserEntity $userEntity): array
    {
        $organizationsBySourceUuid = $this->portalSessionReader->authorizedRepertoireOrganizations($userEntity);
        $themes = $this->repertoireItemDoctrineRepository->portalThemes(array_keys($organizationsBySourceUuid));

        return array_map(static fn (array $theme): array => [
            'uuid' => $theme['uuid'] instanceof Uuid ? $theme['uuid']->toRfc4122() : (string) $theme['uuid'],
            'label' => $theme['label'],
            'color' => $theme['color'],
        ], $themes);
    }

    /** @return array<string, mixed>|null */
    public function findAuthorizedBySlug(UserEntity $userEntity, string $slug): ?array
    {
        $organizationsBySourceUuid = $this->portalSessionReader->authorizedRepertoireOrganizations($userEntity);
        $itemEntity = $this->repertoireItemDoctrineRepository->findOneBy(['slug' => $slug, 'active' => true]);
        if (!$itemEntity instanceof RepertoireItemEntity
            || !isset($organizationsBySourceUuid[$itemEntity->getUuid()->toRfc4122()])) {
            return null;
        }

        return PortalRepertoireResponse::fromEntity(
            $itemEntity,
            $organizationsBySourceUuid[$itemEntity->getUuid()->toRfc4122()],
            $this->mediaEntities($itemEntity),
            true,
        );
    }

    /** @return list<MediaResourceEntity> */
    private function mediaEntities(RepertoireItemEntity $itemEntity): array
    {
        $mediaEntities = [];
        foreach ($itemEntity->getLinkedMediaUuids() as $mediaUuid) {
            if (!Uuid::isValid($mediaUuid)) {
                continue;
            }
            $mediaEntity = $this->entityManager->getRepository(MediaResourceEntity::class)->findOneBy(['uuid' => Uuid::fromString($mediaUuid), 'active' => true]);
            $mediaUrl = $mediaEntity instanceof MediaResourceEntity ? $mediaEntity->getPrimaryUrl() : '';
            $urlParts = parse_url($mediaUrl);
            if ($mediaEntity instanceof MediaResourceEntity
                && false !== $urlParts
                && in_array(strtolower($urlParts['scheme'] ?? ''), ['http', 'https'], true)
                && isset($urlParts['host'])
                && !isset($urlParts['user'])
                && !isset($urlParts['pass'])) {
                $mediaEntities[] = $mediaEntity;
            }
        }

        return $mediaEntities;
    }
}
