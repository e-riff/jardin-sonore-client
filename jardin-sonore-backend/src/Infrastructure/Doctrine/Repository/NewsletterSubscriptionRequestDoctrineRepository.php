<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\NewsletterSubscriptionRequestEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<NewsletterSubscriptionRequestEntity> */
final class NewsletterSubscriptionRequestDoctrineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $managerRegistry)
    {
        parent::__construct($managerRegistry, NewsletterSubscriptionRequestEntity::class);
    }

    public function findByTokenHash(string $tokenHash): ?NewsletterSubscriptionRequestEntity
    {
        return $this->createQueryBuilder('request')->where('request.tokenHash = :hash')->setParameter('hash', $tokenHash)
            ->getQuery()->setHint(Query::HINT_REFRESH, true)->getOneOrNullResult();
    }

    public function findLockedByContact(EmailContactEntity $emailContactEntity): ?NewsletterSubscriptionRequestEntity
    {
        return $this->createQueryBuilder('request')->where('request.emailContact = :contact')->setParameter('contact', $emailContactEntity)
            ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->setHint(Query::HINT_REFRESH, true)->getOneOrNullResult();
    }
}
