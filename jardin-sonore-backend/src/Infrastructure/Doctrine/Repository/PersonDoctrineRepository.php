<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\AddressBook\Person;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use App\Infrastructure\Doctrine\Mapper\PersonMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class PersonDoctrineRepository implements PersonRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PersonMapper $personMapper,
    ) {
    }

    public function findByUuid(Uuid $uuid): ?Person
    {
        $personEntity = $this->entityManager->getRepository(PersonEntity::class)->findOneBy(['uuid' => $uuid]);

        return $personEntity instanceof PersonEntity ? $this->personMapper->toDomain($personEntity) : null;
    }

    public function save(Person $person): void
    {
        $personEntity = $this->entityManager->getRepository(PersonEntity::class)->findOneBy(['uuid' => $person->getUuid()]);

        $this->entityManager->persist($this->personMapper->toEntity(
            person: $person,
            personEntity: $personEntity instanceof PersonEntity ? $personEntity : null,
        ));
        $this->entityManager->flush();
    }
}
