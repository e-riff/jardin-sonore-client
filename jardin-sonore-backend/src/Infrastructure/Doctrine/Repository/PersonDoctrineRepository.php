<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\AddressBook\Person;
use App\Domain\Repository\PersonRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use App\Infrastructure\Doctrine\Mapper\PersonMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<PersonEntity>
 */
final class PersonDoctrineRepository extends ServiceEntityRepository implements PersonRepositoryInterface
{
    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly PersonMapper $personMapper,
    ) {
        parent::__construct($managerRegistry, PersonEntity::class);
    }

    public function findByUuid(Uuid $uuid): ?Person
    {
        $personEntity = $this->findOneBy(['uuid' => $uuid]);

        return $personEntity instanceof PersonEntity ? $this->personMapper->toDomain($personEntity) : null;
    }

    public function save(Person $person): void
    {
        $personEntity = $this->findOneBy(['uuid' => $person->getUuid()]);

        $this->getEntityManager()->persist($this->personMapper->toEntity(
            person: $person,
            personEntity: $personEntity instanceof PersonEntity ? $personEntity : null,
        ));
        $this->getEntityManager()->flush();
    }
}
