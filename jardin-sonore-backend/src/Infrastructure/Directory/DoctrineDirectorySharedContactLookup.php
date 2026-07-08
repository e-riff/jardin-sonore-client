<?php

declare(strict_types=1);

namespace App\Infrastructure\Directory;

use App\Application\Directory\DirectorySharedContactLookupInterface;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;
use App\Infrastructure\Doctrine\Repository\EmailContactEntityRepository;
use App\Infrastructure\Doctrine\Repository\PhoneContactEntityRepository;

final readonly class DoctrineDirectorySharedContactLookup implements DirectorySharedContactLookupInterface
{
    public function __construct(
        private EmailContactEntityRepository $emailContactEntityRepository,
        private PhoneContactEntityRepository $phoneContactEntityRepository,
    ) {
    }

    public function findEmailContactByEmailAddress(string $emailAddress): ?EmailContactEntity
    {
        return $this->emailContactEntityRepository->findOneByEmailAddress($emailAddress);
    }

    public function findPhoneContactByPhoneNumber(string $phoneNumber): ?PhoneContactEntity
    {
        return $this->phoneContactEntityRepository->findOneByPhoneNumber($phoneNumber);
    }
}
