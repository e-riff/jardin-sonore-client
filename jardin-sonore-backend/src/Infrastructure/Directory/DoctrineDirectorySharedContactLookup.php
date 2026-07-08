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

    public function findEmailContactIdByEmailAddress(string $emailAddress): ?int
    {
        $emailContact = $this->entityManager->getRepository(EmailContactEntity::class)->findOneBy([
            'emailAddress' => mb_strtolower(trim($emailAddress)),
        ]);

        return $emailContact instanceof EmailContactEntity ? $emailContact->getId() : null;
    }

    public function findPhoneContactIdByPhoneNumber(string $phoneNumber): ?int
    {
        $phoneContact = $this->entityManager->getRepository(PhoneContactEntity::class)->findOneBy([
            'phoneNumber' => trim($phoneNumber),
        ]);

        return $phoneContact instanceof PhoneContactEntity ? $phoneContact->getId() : null;
    }
}
