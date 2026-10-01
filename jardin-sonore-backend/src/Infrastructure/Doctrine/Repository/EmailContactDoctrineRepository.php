<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\AddressBook\EmailContact;
use App\Domain\Model\ValueObject\EmailAddress;
use App\Domain\Repository\EmailContactRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Mapper\EmailContactMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<EmailContactEntity>
 */
final class EmailContactDoctrineRepository extends ServiceEntityRepository implements EmailContactRepositoryInterface
{
    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly EmailContactMapper $emailContactMapper,
    ) {
        parent::__construct($managerRegistry, EmailContactEntity::class);
    }

    public function findByUuid(Uuid $uuid): ?EmailContact
    {
        $emailContactEntity = $this->findOneBy(['uuid' => $uuid]);

        return $emailContactEntity instanceof EmailContactEntity ? $this->emailContactMapper->toDomain($emailContactEntity) : null;
    }

    public function findByEmailAddress(EmailAddress $emailAddress): ?EmailContact
    {
        $emailContactEntity = $this->findOneBy([
            'emailAddress' => mb_strtolower($emailAddress->value()),
        ]);

        return $emailContactEntity instanceof EmailContactEntity ? $this->emailContactMapper->toDomain($emailContactEntity) : null;
    }

    public function findByUnsubscribeToken(string $unsubscribeToken): ?EmailContact
    {
        $emailContactEntity = $this->findOneBy([
            'unsubscribeToken' => trim($unsubscribeToken),
        ]);

        return $emailContactEntity instanceof EmailContactEntity ? $this->emailContactMapper->toDomain($emailContactEntity) : null;
    }

    public function findEntityByEmailAddress(string $emailAddress): ?EmailContactEntity
    {
        $emailContactEntity = $this->findOneBy([
            'emailAddress' => mb_strtolower(trim($emailAddress)),
        ]);

        return $emailContactEntity instanceof EmailContactEntity ? $emailContactEntity : null;
    }

    public function findEntityById(int $id): ?EmailContactEntity
    {
        $emailContactEntity = $this->find($id);

        return $emailContactEntity instanceof EmailContactEntity ? $emailContactEntity : null;
    }

    public function save(EmailContact $emailContact): void
    {
        $entityManager = $this->getEntityManager();
        $connection = $entityManager->getConnection();
        $connection->transactional(function () use ($emailContact, $entityManager, $connection): void {
            $emailContactEntity = $this->createQueryBuilder('contact')->where('contact.uuid = :uuid')
                ->setParameter('uuid', $emailContact->getUuid(), 'uuid')->getQuery()
                ->setLockMode(LockMode::PESSIMISTIC_WRITE)->setHint(Query::HINT_REFRESH, true)->getOneOrNullResult();
            if ($emailContactEntity instanceof EmailContactEntity && $emailContact->isUnsubscribed()) {
                // Preserve history refreshed under the same lock used by confirmation.
                $emailContactEntity->setOptInNewsletter(false)->setUnsubscribedAt($emailContactEntity->getUnsubscribedAt() ?? $emailContact->getUnsubscribedAt());
                $connection->executeStatement('UPDATE newsletter_subscription_request SET consumed_at = ? WHERE email_contact_id = ? AND consumed_at IS NULL',
                    [$emailContactEntity->getUnsubscribedAt()?->format('Y-m-d H:i:s'), $emailContactEntity->getId()]);
            } else {
                $entityManager->persist($this->emailContactMapper->toEntity(
                    emailContact: $emailContact,
                    emailContactEntity: $emailContactEntity instanceof EmailContactEntity ? $emailContactEntity : null,
                ));
            }
            $entityManager->flush();
        });
    }
}
