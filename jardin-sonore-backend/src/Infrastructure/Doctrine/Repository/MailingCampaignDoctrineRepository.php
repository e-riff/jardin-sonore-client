<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\MailingCampaignEntity;
use App\Infrastructure\Doctrine\Mapper\MailingCampaignMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<MailingCampaignEntity>
 */
final class MailingCampaignDoctrineRepository extends ServiceEntityRepository implements MailingCampaignRepositoryInterface
{
    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly MailingCampaignMapper $mailingCampaignMapper,
    ) {
        parent::__construct($managerRegistry, MailingCampaignEntity::class);
    }

    public function findByUuid(Uuid $uuid): ?MailingCampaign
    {
        $mailingCampaignEntity = $this->findOneBy(['uuid' => $uuid]);

        return $mailingCampaignEntity instanceof MailingCampaignEntity ? $this->mailingCampaignMapper->toDomain($mailingCampaignEntity) : null;
    }

    public function findEntityByUuid(Uuid $uuid): ?MailingCampaignEntity
    {
        $mailingCampaignEntity = $this->findOneBy(['uuid' => $uuid]);

        return $mailingCampaignEntity instanceof MailingCampaignEntity ? $mailingCampaignEntity : null;
    }

    public function findAllOrderedByCreatedAtDesc(): array
    {
        $mailingCampaignEntities = $this->findBy([], [
            'createdAt' => 'DESC',
            'id' => 'DESC',
        ]);

        $mailingCampaigns = [];

        foreach ($mailingCampaignEntities as $mailingCampaignEntity) {
            $mailingCampaigns[] = $this->mailingCampaignMapper->toDomain($mailingCampaignEntity);
        }

        return $mailingCampaigns;
    }

    public function save(MailingCampaign $mailingCampaign): void
    {
        $mailingCampaignEntity = $this->findOneBy(['uuid' => $mailingCampaign->getUuid()]);

        $this->getEntityManager()->persist($this->mailingCampaignMapper->toEntity(
            mailingCampaign: $mailingCampaign,
            mailingCampaignEntity: $mailingCampaignEntity instanceof MailingCampaignEntity ? $mailingCampaignEntity : null,
        ));
        $this->getEntityManager()->flush();
    }

    public function delete(MailingCampaign $mailingCampaign): void
    {
        $mailingCampaignEntity = $this->findOneBy(['uuid' => $mailingCampaign->getUuid()]);

        if (!$mailingCampaignEntity instanceof MailingCampaignEntity) {
            return;
        }

        $this->getEntityManager()->remove($mailingCampaignEntity);
        $this->getEntityManager()->flush();
    }
}
