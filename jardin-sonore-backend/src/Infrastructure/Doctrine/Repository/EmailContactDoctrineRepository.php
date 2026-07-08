<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\AddressBook\EmailContact;
use App\Domain\Model\ValueObject\EmailAddress;
use App\Domain\Repository\EmailContactRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Mapper\EmailContactMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
        $emailContactEntity = $this->findOneBy(['uuid' => $emailContact->getUuid()]);

        $this->getEntityManager()->persist($this->emailContactMapper->toEntity(
            emailContact: $emailContact,
            emailContactEntity: $emailContactEntity instanceof EmailContactEntity ? $emailContactEntity : null,
        ));
        $this->getEntityManager()->flush();
    }
}
