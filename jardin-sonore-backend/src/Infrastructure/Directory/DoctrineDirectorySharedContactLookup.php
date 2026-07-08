<?php

declare(strict_types=1);

namespace App\Infrastructure\Directory;

use App\Application\Directory\DirectorySharedContactLookupInterface;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;
use App\Infrastructure\Doctrine\Repository\EmailContactDoctrineRepository;
use App\Infrastructure\Doctrine\Repository\PhoneContactDoctrineRepository;

final readonly class DoctrineDirectorySharedContactLookup implements DirectorySharedContactLookupInterface
{
    public function __construct(
        private EmailContactDoctrineRepository $emailContactDoctrineRepository,
        private PhoneContactDoctrineRepository $phoneContactDoctrineRepository,
    ) {
    }

    public function findEmailContactIdByEmailAddress(string $emailAddress): ?int
    {
        $emailContact = $this->emailContactDoctrineRepository->findEntityByEmailAddress($emailAddress);

        return $emailContact instanceof EmailContactEntity ? $emailContact->getId() : null;
    }

    public function findPhoneContactIdByPhoneNumber(string $phoneNumber): ?int
    {
        $phoneContact = $this->phoneContactDoctrineRepository->findEntityByPhoneNumber($phoneNumber);

        return $phoneContact instanceof PhoneContactEntity ? $phoneContact->getId() : null;
    }
}
