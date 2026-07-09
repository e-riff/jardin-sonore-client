<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\Mailing\MailingAudienceMask;
use App\Domain\Repository\MailingAudienceMaskRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\MailingAudienceMaskEntity;
use App\Infrastructure\Doctrine\Mapper\MailingAudienceMaskMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
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
        $mailingAudienceMaskEntity = $this->findOneBy(['uuid' => $mailingAudienceMask->getUuid()]);

        $this->getEntityManager()->persist($this->mailingAudienceMaskMapper->toEntity(
            mailingAudienceMask: $mailingAudienceMask,
            mailingAudienceMaskEntity: $mailingAudienceMaskEntity instanceof MailingAudienceMaskEntity ? $mailingAudienceMaskEntity : null,
        ));
        $this->getEntityManager()->flush();
    }
}
