<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Application\Directory\DirectorySharedContactLookupInterface;
use App\Domain\Model\ValueObject\PhoneNumber;
use App\Infrastructure\Doctrine\Entity\ContactDetailsEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

final readonly class SharedContactLinkResolver
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DirectorySharedContactLookupInterface $directorySharedContactLookup,
    ) {
    }

    public function resolveContactDetails(ContactDetailsEntity $contactDetailsEntity): void
    {
        $this->resolveEmailLinks($contactDetailsEntity);
        $this->resolvePhoneLinks($contactDetailsEntity);
    }

    private function resolveEmailLinks(ContactDetailsEntity $contactDetailsEntity): void
    {
        $seenEmailAddresses = [];

        foreach ($contactDetailsEntity->getEmailContactLinks()->toArray() as $emailContactLinkEntity) {
            $emailAddress = $this->normalizeEmail($emailContactLinkEntity->getEmailAddress());

            if (null === $emailAddress) {
                continue;
            }

            if (isset($seenEmailAddresses[$emailAddress])) {
                $contactDetailsEntity->removeEmailContactLink($emailContactLinkEntity);
                $this->entityManager->remove($emailContactLinkEntity);

                continue;
            }

            $seenEmailAddresses[$emailAddress] = true;

            $existingEmailContactEntity = $this->directorySharedContactLookup->findEmailContactByEmailAddress($emailAddress);

            if (
                $existingEmailContactEntity instanceof EmailContactEntity
                && $existingEmailContactEntity !== $emailContactLinkEntity->getEmailContact()
            ) {
                $emailContactLinkEntity->setEmailContact($existingEmailContactEntity);
            }
        }
    }

    private function resolvePhoneLinks(ContactDetailsEntity $contactDetailsEntity): void
    {
        $seenPhoneNumbers = [];

        foreach ($contactDetailsEntity->getPhoneContactLinks()->toArray() as $phoneContactLinkEntity) {
            $phoneNumber = $this->normalizePhone($phoneContactLinkEntity->getPhoneNumber());

            if (null === $phoneNumber) {
                continue;
            }

            if (isset($seenPhoneNumbers[$phoneNumber])) {
                $contactDetailsEntity->removePhoneContactLink($phoneContactLinkEntity);
                $this->entityManager->remove($phoneContactLinkEntity);

                continue;
            }

            $seenPhoneNumbers[$phoneNumber] = true;

            $existingPhoneContactEntity = $this->directorySharedContactLookup->findPhoneContactByPhoneNumber($phoneNumber);

            if (
                $existingPhoneContactEntity instanceof PhoneContactEntity
                && $existingPhoneContactEntity !== $phoneContactLinkEntity->getPhoneContact()
            ) {
                $phoneContactLinkEntity->setPhoneContact($existingPhoneContactEntity);
            }
        }
    }

    private function normalizeEmail(?string $emailAddress): ?string
    {
        if (null === $emailAddress) {
            return null;
        }

        $emailAddress = mb_strtolower(trim($emailAddress));

        return '' === $emailAddress ? null : $emailAddress;
    }

    private function normalizePhone(?string $phoneNumber): ?string
    {
        if (null === $phoneNumber) {
            return null;
        }

        $phoneNumber = trim($phoneNumber);

        if ('' === $phoneNumber) {
            return null;
        }

        try {
            return PhoneNumber::normalize($phoneNumber);
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
