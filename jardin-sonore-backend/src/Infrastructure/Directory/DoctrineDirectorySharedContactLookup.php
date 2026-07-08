<?php

declare(strict_types=1);

namespace App\Infrastructure\Directory;

use App\Application\Directory\DirectorySharedContactLookupInterface;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineDirectorySharedContactLookup implements DirectorySharedContactLookupInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function findEmailContactByEmailAddress(string $emailAddress): ?EmailContactEntity
    {
        $emailContact = $this->entityManager->getRepository(EmailContactEntity::class)->findOneBy([
            'emailAddress' => mb_strtolower(trim($emailAddress)),
        ]);

        return $emailContact instanceof EmailContactEntity ? $emailContact : null;
    }

    public function findPhoneContactByPhoneNumber(string $phoneNumber): ?PhoneContactEntity
    {
        $phoneContact = $this->entityManager->getRepository(PhoneContactEntity::class)->findOneBy([
            'phoneNumber' => trim($phoneNumber),
        ]);

        return $phoneContact instanceof PhoneContactEntity ? $phoneContact : null;
    }
}
