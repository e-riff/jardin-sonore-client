<?php

declare(strict_types=1);

namespace App\Application\Portal;

use App\Application\Session\RepertoireBlockView;
use App\Application\Session\SessionSequenceView;
use App\Domain\Model\Session\SessionSequence;
use App\Domain\Model\Session\SessionSequenceMedia;
use App\Domain\Repository\InstrumentRepositoryInterface;
use App\Domain\Repository\MediaResourceRepositoryInterface;
use App\Domain\Repository\RepertoireItemRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use App\Infrastructure\Doctrine\Entity\UserOrganizationAccessEntity;
use App\Infrastructure\Doctrine\Mapper\SessionSummaryMapper;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use ValueError;

final readonly class PortalSessionReader
{
    private const int PAGE_SIZE = 20;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private SessionSummaryMapper $sessionSummaryMapper,
        private RepertoireItemRepositoryInterface $repertoireItemRepository,
        private MediaResourceRepositoryInterface $mediaResourceRepository,
        private InstrumentRepositoryInterface $instrumentRepository,
    ) {
    }

    /** @return list<OrganizationEntity> */
    public function authorizedOrganizations(UserEntity $userEntity): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('organization')
            ->from(OrganizationEntity::class, 'organization')
            ->innerJoin(UserOrganizationAccessEntity::class, 'access', 'WITH', 'access.organization = organization')
            ->where('access.user = :user')
            ->andWhere('access.active = :active')
            ->setParameter('user', $userEntity)
            ->setParameter('active', true)
            ->orderBy('organization.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function hasActiveOrganizationAccess(UserEntity $userEntity): bool
    {
        return 0 < (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(access.id)')
            ->from(UserOrganizationAccessEntity::class, 'access')
            ->where('access.user = :user')
            ->andWhere('access.active = :active')
            ->setParameter('user', $userEntity)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<array{uuid: string, label: string, color: string}> */
    public function availableThemes(UserEntity $userEntity): array
    {
        $themes = $this->authorizedSessionsQueryBuilder($userEntity)
            ->select('DISTINCT theme.uuid AS uuid, theme.label AS label, theme.color AS color')
            ->innerJoin('session.themes', 'theme')
            ->orderBy('theme.label', 'ASC')
            ->getQuery()
            ->getResult();

        return array_map(static fn (array $theme): array => [
            'uuid' => $theme['uuid'] instanceof Uuid ? $theme['uuid']->toRfc4122() : (string) $theme['uuid'],
            'label' => $theme['label'],
            'color' => $theme['color'],
        ], $themes);
    }

    /** @return array{items: list<SessionSummaryEntity>, total: int, page: int, pageSize: int} */
    public function paginated(UserEntity $userEntity, PortalListCriteria $criteria): array
    {
        $page = $criteria->page;
        $queryBuilder = $this->authorizedSessionsQueryBuilder($userEntity, $criteria->organizationUuid);
        if (null !== $criteria->query) {
            $queryBuilder
                ->leftJoin('session.themes', 'searchTheme')
                ->andWhere($queryBuilder->expr()->orX('LOWER(session.title) LIKE :query', 'LOWER(searchTheme.label) LIKE :query'))
                ->setParameter('query', '%' . mb_strtolower($criteria->query) . '%');
        }
        if ([] !== $criteria->themeUuids) {
            $queryBuilder->innerJoin('session.themes', 'filterTheme');
            $themeCondition = $queryBuilder->expr()->orX();
            foreach ($criteria->themeUuids as $index => $themeUuid) {
                $parameterName = "themeUuid{$index}";
                $themeCondition->add("filterTheme.uuid = :{$parameterName}");
                $queryBuilder->setParameter($parameterName, Uuid::fromString($themeUuid), UuidType::NAME);
            }
            $queryBuilder->andWhere($themeCondition);
        }
        if ('title' === $criteria->sort) {
            $queryBuilder->orderBy('session.title', strtoupper($criteria->direction));
        } else {
            $queryBuilder->orderBy('session.sessionDate', strtoupper($criteria->direction));
        }
        $queryBuilder->addOrderBy('session.id', 'DESC');
        $total = (int) (clone $queryBuilder)
            ->select('COUNT(DISTINCT session.id)')
            ->getQuery()
            ->getSingleScalarResult();
        $items = $queryBuilder
            ->select('DISTINCT session')
            ->setFirstResult(($page - 1) * self::PAGE_SIZE)
            ->setMaxResults(self::PAGE_SIZE)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total, 'page' => $page, 'pageSize' => self::PAGE_SIZE];
    }

    public function findAuthorizedBySlug(UserEntity $userEntity, string $sessionSlug): ?SessionSummaryEntity
    {
        if ('' === trim($sessionSlug)) {
            return null;
        }

        $sessionEntity = $this->authorizedSessionsQueryBuilder($userEntity)
            ->andWhere('session.slug = :slug')
            ->setParameter('slug', $sessionSlug)
            ->getQuery()
            ->getOneOrNullResult();

        return $sessionEntity instanceof SessionSummaryEntity ? $sessionEntity : null;
    }

    /** @return array<string, list<OrganizationEntity>> */
    public function authorizedRepertoireOrganizations(UserEntity $userEntity, ?string $organizationUuid = null): array
    {
        $authorizedOrganizationEntities = $this->authorizedOrganizations($userEntity);
        $authorizedOrganizationUuids = array_fill_keys(array_map(
            static fn (OrganizationEntity $organizationEntity): string => $organizationEntity->getUuid()->toRfc4122(),
            $authorizedOrganizationEntities,
        ), true);
        $organizationsBySourceUuid = [];
        $sessions = $this->authorizedSessionsQueryBuilder($userEntity)->getQuery()->getResult();
        foreach ($sessions as $sessionSummaryEntity) {
            if (!$sessionSummaryEntity instanceof SessionSummaryEntity) {
                continue;
            }
            $sessionOrganizations = [];
            foreach ($sessionSummaryEntity->getOrganizationShares() as $organizationShare) {
                $organizationEntity = $organizationShare->getOrganization();
                $uuid = $organizationEntity->getUuid()->toRfc4122();
                if (isset($authorizedOrganizationUuids[$uuid])) {
                    $sessionOrganizations[$uuid] = $organizationEntity;
                }
            }
            foreach ($sessionSummaryEntity->getSequences() as $sequence) {
                if (!is_array($sequence)
                    || 'repertoire_item' !== ($sequence['sourceKind'] ?? null)
                    || !is_string($sequence['sourceUuid'] ?? null)
                    || !Uuid::isValid($sequence['sourceUuid'])) {
                    continue;
                }
                $sourceUuid = $sequence['sourceUuid'];
                foreach ($sessionOrganizations as $uuid => $organizationEntity) {
                    $organizationsBySourceUuid[$sourceUuid][$uuid] = $organizationEntity;
                }
            }
        }

        if (null !== $organizationUuid) {
            $organizationsBySourceUuid = array_filter(
                $organizationsBySourceUuid,
                static fn (array $organizations): bool => isset($organizations[$organizationUuid]),
            );
        }

        return array_map(static fn (array $organizations): array => array_values($organizations), $organizationsBySourceUuid);
    }

    /** @return list<array<string, mixed>> */
    public function detailSequences(SessionSummaryEntity $sessionSummaryEntity): array
    {
        try {
            $sessionSequences = $this->sessionSummaryMapper->toDomain($sessionSummaryEntity)->getSequences();
        } catch (InvalidArgumentException|ValueError) {
            return $sessionSummaryEntity->getSequences();
        }

        return array_map(function (SessionSequence $sessionSequence): array {
            $repertoireItem = null === $sessionSequence->sourceUuid
                ? null
                : $this->repertoireItemRepository->findByUuid($sessionSequence->sourceUuid);
            $sessionSequenceView = SessionSequenceView::fromDomain(
                $sessionSequence,
                $repertoireItem,
                mediaResourceRepository: $this->mediaResourceRepository,
            );

            return [
                'uuid' => $sessionSequenceView->uuid->toRfc4122(),
                'type' => $sessionSequenceView->type->value,
                'title' => $sessionSequenceView->title,
                'subtitle' => $sessionSequenceView->subtitle,
                'body' => $sessionSequenceView->body,
                'lyrics' => $sessionSequenceView->lyrics,
                'gestures' => $sessionSequenceView->gestures,
                'notes' => $sessionSequenceView->notes,
                'media' => array_map(static fn (SessionSequenceMedia $media): array => $media->toArray(), $sessionSequenceView->media),
                'documentMedia' => array_map(static fn (SessionSequenceMedia $media): array => $media->toArray(), $sessionSequenceView->documentMedia),
                'showLyricsByDefault' => $sessionSequenceView->showLyricsByDefault,
                'role' => $sessionSequenceView->role,
                'instrumentUuids' => $sessionSequenceView->instrumentUuids,
                'sourceUuid' => $sessionSequenceView->sourceUuid?->toRfc4122(),
                'sourceKind' => $sessionSequenceView->sourceKind?->value,
                'sourceTitle' => $sessionSequenceView->sourceTitle,
                'generalInstructions' => $sessionSequenceView->generalInstructions,
                'contentBlocks' => array_map(static fn (RepertoireBlockView $contentBlock): array => [
                    'kind' => $contentBlock->kind->value,
                    'text' => $contentBlock->text,
                    'gesture' => $contentBlock->gesture,
                ], $sessionSequenceView->contentBlocks),
            ];
        }, $sessionSequences);
    }

    /** @return list<string> */
    public function instrumentNames(SessionSummaryEntity $sessionSummaryEntity): array
    {
        return array_values(array_filter(array_map(
            function (string $instrumentUuid): ?string {
                if (!Uuid::isValid($instrumentUuid)) {
                    return null;
                }

                return $this->instrumentRepository->findByUuid(Uuid::fromString($instrumentUuid))?->getName();
            },
            $sessionSummaryEntity->getInstrumentUuids(),
        )));
    }

    private function authorizedSessionsQueryBuilder(UserEntity $userEntity, ?string $organizationUuid = null): \Doctrine\ORM\QueryBuilder
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('session')
            ->from(SessionSummaryEntity::class, 'session')
            ->innerJoin('session.organizationShares', 'organizationShare')
            ->innerJoin('organizationShare.organization', 'organization')
            ->innerJoin(UserOrganizationAccessEntity::class, 'access', 'WITH', 'access.organization = organization')
            ->where('access.user = :user')
            ->andWhere('access.active = :active')
            ->andWhere('session.published = :published')
            ->setParameter('user', $userEntity)
            ->setParameter('active', true)
            ->setParameter('published', true);

        if (null !== $organizationUuid) {
            if (!Uuid::isValid($organizationUuid)) {
                $queryBuilder->andWhere('1 = 0');
            } else {
                $queryBuilder
                    ->andWhere('organization.uuid = :organizationUuid')
                    ->setParameter('organizationUuid', Uuid::fromString($organizationUuid), UuidType::NAME);
            }
        }

        return $queryBuilder;
    }
}
