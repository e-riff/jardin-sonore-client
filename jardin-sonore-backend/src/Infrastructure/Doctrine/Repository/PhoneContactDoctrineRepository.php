<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\AddressBook\PhoneContact;
use App\Domain\Repository\PhoneContactRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;
use App\Infrastructure\Doctrine\Mapper\PhoneContactMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class PhoneContactDoctrineRepository implements PhoneContactRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PhoneContactMapper $phoneContactMapper,
    ) {
    }

    public function findByUuid(Uuid $uuid): ?PhoneContact
    {
        $phoneContactEntity = $this->entityManager->getRepository(PhoneContactEntity::class)->findOneBy(['uuid' => $uuid]);

        return $phoneContactEntity instanceof PhoneContactEntity ? $this->phoneContactMapper->toDomain($phoneContactEntity) : null;
    }

    public function save(PhoneContact $phoneContact): void
    {
        $phoneContactEntity = $this->entityManager->getRepository(PhoneContactEntity::class)->findOneBy(['uuid' => $phoneContact->getUuid()]);

        $this->entityManager->persist($this->phoneContactMapper->toEntity(
            phoneContact: $phoneContact,
            phoneContactEntity: $phoneContactEntity instanceof PhoneContactEntity ? $phoneContactEntity : null,
        ));
        $this->entityManager->flush();
    }
}
