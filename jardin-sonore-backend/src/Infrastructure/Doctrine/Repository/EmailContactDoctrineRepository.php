<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\AddressBook\EmailContact;
use App\Domain\Model\ValueObject\EmailAddress;
use App\Domain\Repository\EmailContactRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Mapper\EmailContactMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class EmailContactDoctrineRepository implements EmailContactRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EmailContactMapper $emailContactMapper,
    ) {
    }

    public function findByUuid(Uuid $uuid): ?EmailContact
    {
        $emailContactEntity = $this->entityManager->getRepository(EmailContactEntity::class)->findOneBy(['uuid' => $uuid]);

        return $emailContactEntity instanceof EmailContactEntity ? $this->emailContactMapper->toDomain($emailContactEntity) : null;
    }

    public function findByEmailAddress(EmailAddress $emailAddress): ?EmailContact
    {
        $emailContactEntity = $this->entityManager->getRepository(EmailContactEntity::class)->findOneBy([
            'emailAddress' => mb_strtolower($emailAddress->value()),
        ]);

        return $emailContactEntity instanceof EmailContactEntity ? $this->emailContactMapper->toDomain($emailContactEntity) : null;
    }

    public function findByUnsubscribeToken(string $unsubscribeToken): ?EmailContact
    {
        $emailContactEntity = $this->entityManager->getRepository(EmailContactEntity::class)->findOneBy([
            'unsubscribeToken' => trim($unsubscribeToken),
        ]);

        return $emailContactEntity instanceof EmailContactEntity ? $this->emailContactMapper->toDomain($emailContactEntity) : null;
    }

    public function save(EmailContact $emailContact): void
    {
        $emailContactEntity = $this->entityManager->getRepository(EmailContactEntity::class)->findOneBy(['uuid' => $emailContact->getUuid()]);

        $this->entityManager->persist($this->emailContactMapper->toEntity(
            emailContact: $emailContact,
            emailContactEntity: $emailContactEntity instanceof EmailContactEntity ? $emailContactEntity : null,
        ));
        $this->entityManager->flush();
    }
}
