<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\Mailing\MailingAudienceMask;
use App\Domain\Repository\MailingAudienceMaskRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\MailingAudienceMaskEntity;
use App\Infrastructure\Doctrine\Mapper\MailingAudienceMaskMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use DomainException;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<MailingAudienceMaskEntity>
 */
final class MailingAudienceMaskDoctrineRepository extends ServiceEntityRepository implements MailingAudienceMaskRepositoryInterface
{
    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly MailingAudienceMaskMapper $mailingAudienceMaskMapper,
    ) {
        parent::__construct($managerRegistry, MailingAudienceMaskEntity::class);
    }

    public function findNewsletterMask(): ?MailingAudienceMask
    {
        $entity = $this->findOneBy(['reservedRole' => 'newsletter']);

        return $entity instanceof MailingAudienceMaskEntity ? $this->mailingAudienceMaskMapper->toDomain($entity) : null;
    }

    public function findByUuid(Uuid $uuid): ?MailingAudienceMask
    {
        $mailingAudienceMaskEntity = $this->findOneBy(['uuid' => $uuid]);

        return $mailingAudienceMaskEntity instanceof MailingAudienceMaskEntity
            ? $this->mailingAudienceMaskMapper->toDomain($mailingAudienceMaskEntity)
            : null;
    }

    public function findAllOrderedByUpdatedAtDesc(): array
    {
        $mailingAudienceMaskEntities = $this->findBy([], [
            'updatedAt' => 'DESC',
            'id' => 'DESC',
        ]);
        $mailingAudienceMasks = [];

        foreach ($mailingAudienceMaskEntities as $mailingAudienceMaskEntity) {
            $mailingAudienceMasks[] = $this->mailingAudienceMaskMapper->toDomain($mailingAudienceMaskEntity);
        }

        return $mailingAudienceMasks;
    }

    public function save(MailingAudienceMask $mailingAudienceMask): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->getConnection()->transactional(function () use ($entityManager, $mailingAudienceMask): void {
            $entity = $this->lockedEntity($mailingAudienceMask->getUuid());
            $entityManager->persist($this->mailingAudienceMaskMapper->toEntity($mailingAudienceMask, $entity));
            $entityManager->flush();
        });
    }

    public function remove(MailingAudienceMask $mailingAudienceMask): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->getConnection()->transactional(function () use ($entityManager, $mailingAudienceMask): void {
            $entity = $this->lockedEntity($mailingAudienceMask->getUuid());
            if (null === $entity) {
                return;
            }
            if (null !== $entity->getReservedRole()) {
                throw new DomainException('The newsletter audience mask cannot be deleted.');
            }
            $entityManager->remove($entity);
            $entityManager->flush();
        });
    }

    private function lockedEntity(Uuid $uuid): ?MailingAudienceMaskEntity
    {
        return $this->createQueryBuilder('mask')->where('mask.uuid = :uuid')->setParameter('uuid', $uuid, 'uuid')
            ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->setHint(Query::HINT_REFRESH, true)->getOneOrNullResult();
    }
}
