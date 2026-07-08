<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\MailingCampaignEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<MailingCampaignEntity>
 */
final class MailingCampaignEntityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, MailingCampaignEntity::class);
    }

    public function findOneByUuid(Uuid $uuid): ?MailingCampaignEntity
    {
        $mailingCampaign = $this->findOneBy(['uuid' => $uuid]);

        return $mailingCampaign instanceof MailingCampaignEntity ? $mailingCampaign : null;
    }
}
