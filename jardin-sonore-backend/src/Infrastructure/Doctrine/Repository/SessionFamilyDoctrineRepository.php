<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\Session\SessionFamily;
use App\Domain\Repository\SessionFamilyRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\SessionFamilyEntity;
use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use App\Infrastructure\Doctrine\Entity\ThemeEntity;
use App\Infrastructure\Doctrine\Mapper\SessionFamilyMapper;
use App\Infrastructure\Doctrine\Mapper\SessionSummaryMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Symfony\Component\Uid\Uuid;

/** @extends ServiceEntityRepository<SessionFamilyEntity> */
final class SessionFamilyDoctrineRepository extends ServiceEntityRepository implements SessionFamilyRepositoryInterface
{
    public function __construct(ManagerRegistry $managerRegistry, private readonly SessionFamilyMapper $familyMapper, private readonly SessionSummaryMapper $sessionMapper)
    {
        parent::__construct($managerRegistry, SessionFamilyEntity::class);
    }

    public function findByUuid(Uuid $uuid): ?SessionFamily
    {
        $entity = $this->findOneBy(['uuid' => $uuid]);

        return $entity instanceof SessionFamilyEntity ? $this->familyMapper->toDomain($entity) : null;
    }

    public function listFamilies(): array
    {
        $entities = $this->createQueryBuilder('family')
            ->leftJoin('family.themes', 'theme')->addSelect('theme')
            ->orderBy('family.name', 'ASC')->addOrderBy('family.id', 'ASC')
            ->getQuery()->getResult();

        return array_map($this->familyMapper->toDomain(...), $entities);
    }

    public function save(SessionFamily $family): void
    {
        $entity = $this->findOneBy(['uuid' => $family->uuid]) ?? (new SessionFamilyEntity())->setUuid($family->uuid);
        $themes = [];
        foreach ($family->themes as $theme) {
            $themeEntity = $this->getEntityManager()->getRepository(ThemeEntity::class)->findOneBy(['uuid' => $theme->getUuid()]);
            if (!$themeEntity instanceof ThemeEntity) {
                throw new LogicException('Session family theme not found.');
            }
            $themes[] = $themeEntity;
        }
        $entity->setName($family->name);
        $entity->replaceThemes($themes);
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }

    public function sessions(Uuid $familyUuid): array
    {
        $family = $this->findOneBy(['uuid' => $familyUuid]);
        if (!$family instanceof SessionFamilyEntity) {
            throw new LogicException('Session family not found.');
        }
        $sessions = $this->getEntityManager()->getRepository(SessionSummaryEntity::class)->createQueryBuilder('session')
            ->leftJoin('session.themes', 'theme')->addSelect('theme')
            ->leftJoin('session.organizationShares', 'share')->addSelect('share')
            ->leftJoin('share.organization', 'organization')->addSelect('organization')
            ->where('session.family = :family')
            ->setParameter('family', $family)
            ->orderBy('session.sessionDate', 'DESC')->addOrderBy('session.id', 'DESC')
            ->getQuery()->getResult();

        return array_map($this->sessionMapper->toDomain(...), $sessions);
    }

    public function assignSession(Uuid $sessionUuid, ?Uuid $familyUuid): void
    {
        $session = $this->getEntityManager()->getRepository(SessionSummaryEntity::class)->findOneBy(['uuid' => $sessionUuid]);
        $family = null === $familyUuid ? null : $this->findOneBy(['uuid' => $familyUuid]);
        if (!$session instanceof SessionSummaryEntity || (null !== $familyUuid && !$family instanceof SessionFamilyEntity)) {
            throw new LogicException('Session or family not found.');
        }
        $session->setFamily($family);
        $this->getEntityManager()->flush();
    }

    public function availableSessions(): array
    {
        $sessions = $this->getEntityManager()->getRepository(SessionSummaryEntity::class)->createQueryBuilder('session')
            ->where('session.family IS NULL')->orderBy('session.title', 'ASC')->getQuery()->getResult();

        return array_map(static fn (SessionSummaryEntity $session): array => [
            'uuid' => $session->getUuid(), 'title' => $session->getTitle(), 'sessionDate' => $session->getSessionDate(),
        ], $sessions);
    }
}
