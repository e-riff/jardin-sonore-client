<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Repository;

use App\Domain\Model\AddressBook\PhoneContact;
use App\Domain\Repository\PhoneContactRepositoryInterface;
use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;
use App\Infrastructure\Doctrine\Mapper\PhoneContactMapper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<PhoneContactEntity>
 */
final class PhoneContactDoctrineRepository extends ServiceEntityRepository implements PhoneContactRepositoryInterface
{
    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly PhoneContactMapper $phoneContactMapper,
    ) {
        parent::__construct($managerRegistry, PhoneContactEntity::class);
    }

    public function findByUuid(Uuid $uuid): ?PhoneContact
    {
        $phoneContactEntity = $this->findOneBy(['uuid' => $uuid]);

        return $phoneContactEntity instanceof PhoneContactEntity ? $this->phoneContactMapper->toDomain($phoneContactEntity) : null;
    }

    public function save(PhoneContact $phoneContact): void
    {
        $phoneContactEntity = $this->findOneBy(['uuid' => $phoneContact->getUuid()]);

        $this->getEntityManager()->persist($this->phoneContactMapper->toEntity(
            phoneContact: $phoneContact,
            phoneContactEntity: $phoneContactEntity instanceof PhoneContactEntity ? $phoneContactEntity : null,
        ));
        $this->getEntityManager()->flush();
    }
}
