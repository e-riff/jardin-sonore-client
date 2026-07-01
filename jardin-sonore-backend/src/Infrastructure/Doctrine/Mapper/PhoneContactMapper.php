<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\AddressBook\PhoneContact;
use App\Domain\Model\AddressBook\PhoneContactType;
use App\Domain\Model\ValueObject\PhoneNumber;
use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;

final readonly class PhoneContactMapper
{
    public function toDomain(PhoneContactEntity $phoneContactEntity): PhoneContact
    {
        return new PhoneContact(
            phoneNumber: new PhoneNumber($phoneContactEntity->getPhoneNumber()),
            label: null,
            type: PhoneContactType::MAIN,
            active: $phoneContactEntity->isActive(),
            uuid: $phoneContactEntity->getUuid(),
            id: $phoneContactEntity->getId(),
        );
    }

    public function toEntity(PhoneContact $phoneContact, ?PhoneContactEntity $phoneContactEntity = null): PhoneContactEntity
    {
        $phoneContactEntity ??= new PhoneContactEntity();

        $phoneContactEntity
            ->setUuid($phoneContact->getUuid())
            ->setPhoneNumber($phoneContact->getPhoneNumber()->value())
            ->setActive($phoneContact->isActive());

        return $phoneContactEntity;
    }
}
