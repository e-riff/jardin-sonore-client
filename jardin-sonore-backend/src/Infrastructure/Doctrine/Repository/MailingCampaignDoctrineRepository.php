<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\MailingCampaignEntity;
use App\Infrastructure\Doctrine\Mapper\MailingCampaignMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class MailingCampaignDoctrineRepository implements MailingCampaignRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailingCampaignMapper $mailingCampaignMapper,
    ) {
    }

    public function findByUuid(Uuid $uuid): ?MailingCampaign
    {
        $mailingCampaignEntity = $this->entityManager->getRepository(MailingCampaignEntity::class)->findOneBy(['uuid' => $uuid]);

        return $mailingCampaignEntity instanceof MailingCampaignEntity ? $this->mailingCampaignMapper->toDomain($mailingCampaignEntity) : null;
    }

    public function findAllOrderedByCreatedAtDesc(): array
    {
        $mailingCampaignEntities = $this->entityManager->getRepository(MailingCampaignEntity::class)->findBy([], [
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
        $mailingCampaignEntity = $this->entityManager->getRepository(MailingCampaignEntity::class)->findOneBy(['uuid' => $mailingCampaign->getUuid()]);

        $this->entityManager->persist($this->mailingCampaignMapper->toEntity(
            mailingCampaign: $mailingCampaign,
            mailingCampaignEntity: $mailingCampaignEntity instanceof MailingCampaignEntity ? $mailingCampaignEntity : null,
        ));
        $this->entityManager->flush();
    }
}
