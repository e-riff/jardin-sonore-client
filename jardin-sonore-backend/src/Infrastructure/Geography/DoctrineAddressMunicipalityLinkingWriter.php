<?php

declare(strict_types=1);

namespace App\Infrastructure\Geography;

use App\Application\Geography\AddressMunicipalityLinkingWriterInterface;
use App\Infrastructure\Doctrine\Entity\AddressContactEntity;
use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

final readonly class DoctrineAddressMunicipalityLinkingWriter implements AddressMunicipalityLinkingWriterInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function linkAddressContactToMunicipality(int $addressContactId, int $municipalityId): bool
    {
        try {
            $addressContact = $this->entityManager->find(AddressContactEntity::class, $addressContactId);
            $municipality = $this->entityManager->find(MunicipalityEntity::class, $municipalityId);

            if (!$addressContact instanceof AddressContactEntity || !$municipality instanceof MunicipalityEntity) {
                return false;
            }

            $addressContact->setMunicipality($municipality);
            $this->entityManager->persist($addressContact);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }

    public function clear(): void
    {
        $this->entityManager->clear();
    }
}
