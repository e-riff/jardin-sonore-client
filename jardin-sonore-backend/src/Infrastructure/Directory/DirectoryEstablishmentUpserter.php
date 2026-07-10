<?php

declare(strict_types=1);

namespace App\Infrastructure\Directory;

use App\Application\Directory\DirectoryEstablishmentImportItem;
use App\Application\Directory\DirectoryMunicipalityLookupInterface;
use App\Application\Directory\DirectorySharedContactLookupInterface;
use App\Domain\Model\AddressBook\AddressContactType;
use App\Domain\Model\AddressBook\ContactDataSource;
use App\Domain\Model\AddressBook\CustomerStatus;
use App\Domain\Model\AddressBook\EmailContactType;
use App\Domain\Model\AddressBook\OrganizationType;
use App\Domain\Model\AddressBook\PhoneContactType;
use App\Domain\Model\ValueObject\PhoneNumber;
use App\Infrastructure\Doctrine\Entity\AddressContactEntity;
use App\Infrastructure\Doctrine\Entity\ContactDetailsEntity;
use App\Infrastructure\Doctrine\Entity\DirectoryImportLinkEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactLinkEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactLinkEntity;
use App\Infrastructure\Doctrine\Repository\DirectoryImportLinkEntityRepository;
use App\Infrastructure\Doctrine\Repository\EmailContactDoctrineRepository;
use App\Infrastructure\Doctrine\Repository\MunicipalityDoctrineRepository;
use App\Infrastructure\Doctrine\Repository\PhoneContactDoctrineRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

final readonly class DirectoryEstablishmentUpserter
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DirectoryMunicipalityLookupInterface $directoryMunicipalityLookup,
        private DirectorySharedContactLookupInterface $directorySharedContactLookup,
        private EmailContactDoctrineRepository $emailContactDoctrineRepository,
        private PhoneContactDoctrineRepository $phoneContactDoctrineRepository,
        private MunicipalityDoctrineRepository $municipalityDoctrineRepository,
        private DirectoryImportLinkEntityRepository $directoryImportLinkEntityRepository,
    ) {
    }

    public function hydrateOrganization(OrganizationEntity $organization, DirectoryEstablishmentImportItem $item): void
    {
        if (null !== $item->name) {
            $organization->setName($item->name);
        }

        if (null !== $item->websiteUrl) {
            $organization->setWebsiteUrl($item->websiteUrl);
        }

        $organizationType = $this->mapOrganizationType($item);
        if (null === $organization->getType() && null !== $organizationType) {
            $organization->setType($organizationType);
        }

        if (null === $organization->getCustomerStatus()) {
            $organization->setCustomerStatus(CustomerStatus::PROSPECT);
        }
    }

    /**
     * @return array{bool, bool}
     */
    public function upsertEmailLink(ContactDetailsEntity $contactDetails, DirectoryEstablishmentImportItem $item, bool $apply): array
    {
        $emailAddress = $this->normalizeEmail($item->emailAddress);

        if (null === $emailAddress) {
            return [false, false];
        }

        foreach ($contactDetails->getEmailContactLinks() as $existingLink) {
            if ($emailAddress === $this->normalizeEmail($existingLink->getEmailAddress())) {
                return [false, true];
            }
        }

        $emailContactId = $this->directorySharedContactLookup->findEmailContactIdByEmailAddress($emailAddress);
        $emailContact = null !== $emailContactId ? $this->emailContactDoctrineRepository->findEntityById($emailContactId) : null;
        $created = false;

        if (!$emailContact instanceof EmailContactEntity) {
            $emailContact = (new EmailContactEntity())
                ->setEmailAddress($emailAddress)
                ->setSource(ContactDataSource::DIRECTORY_IMPORT)
                ->setOptInNewsletter(true);
            $created = true;
        }

        $emailContactLink = (new EmailContactLinkEntity())
            ->setEmailContact($emailContact)
            ->setType(EmailContactType::MAIN)
            ->setActive(true);

        $contactDetails->addEmailContactLink($emailContactLink);

        if ($apply) {
            $this->entityManager->persist($contactDetails);
        }

        return [$created, !$created];
    }

    /**
     * @return array{bool, bool}
     */
    public function upsertPhoneLink(ContactDetailsEntity $contactDetails, DirectoryEstablishmentImportItem $item, bool $apply): array
    {
        $phoneNumber = $this->normalizePhone($item->phoneNumber);

        if (null === $phoneNumber) {
            return [false, false];
        }

        foreach ($contactDetails->getPhoneContactLinks() as $existingLink) {
            if ($phoneNumber === $this->normalizePhone($existingLink->getPhoneNumber())) {
                return [false, true];
            }
        }

        $phoneContactId = $this->directorySharedContactLookup->findPhoneContactIdByPhoneNumber($phoneNumber);
        $phoneContact = null !== $phoneContactId ? $this->phoneContactDoctrineRepository->findEntityById($phoneContactId) : null;
        $created = false;

        if (!$phoneContact instanceof PhoneContactEntity) {
            $phoneContact = (new PhoneContactEntity())->setPhoneNumber($phoneNumber);
            $created = true;
        }

        $phoneContactLink = (new PhoneContactLinkEntity())
            ->setPhoneContact($phoneContact)
            ->setType(PhoneContactType::MAIN)
            ->setActive(true);

        $contactDetails->addPhoneContactLink($phoneContactLink);

        if ($apply) {
            $this->entityManager->persist($contactDetails);
        }

        return [$created, !$created];
    }

    public function upsertAddressContact(ContactDetailsEntity $contactDetails, DirectoryEstablishmentImportItem $item): void
    {
        if (null === $item->address && null === $item->commune) {
            return;
        }

        $addressContact = $contactDetails->getAddressContacts()->first();

        if (!$addressContact instanceof AddressContactEntity) {
            $addressContact = (new AddressContactEntity())
                ->setType(AddressContactType::MAIN)
                ->setActive(true);
            $contactDetails->addAddressContact($addressContact);
        }

        if (null !== $item->address) {
            $addressContact->setAddress($item->address);
        }

        if (null !== $item->commune) {
            $addressContact->setCity($item->commune);
        }

        $postalCode = $this->extractPostalCode($item->address);
        if (null !== $postalCode) {
            $addressContact->setPostalCode($postalCode);
        }

        $municipalityId = $this->directoryMunicipalityLookup->findIdByNameAndPostalCode(
            commune: $item->commune,
            postalCode: $postalCode,
        );
        $municipality = null !== $municipalityId ? $this->municipalityDoctrineRepository->findEntityById($municipalityId) : null;
        $addressContact->setMunicipality($municipality);
    }

    public function persistImportLink(
        OrganizationEntity $organization,
        ?int $existingImportLinkId,
        DirectoryEstablishmentImportItem $item,
        string $source,
    ): void {
        $existingImportLink = null !== $existingImportLinkId ? $this->directoryImportLinkEntityRepository->findById($existingImportLinkId) : null;
        $importLink = $existingImportLink instanceof DirectoryImportLinkEntity ? $existingImportLink : new DirectoryImportLinkEntity();
        $importLink
            ->setDirectoryEntry($organization)
            ->setSource($source)
            ->setExternalId($item->externalId)
            ->setExternalOrganizationId($item->externalOrganizationId)
            ->setPayloadHash(hash('sha256', json_encode($item->rawPayload, JSON_THROW_ON_ERROR)));

        $this->entityManager->persist($importLink);
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

    private function extractPostalCode(?string $address): ?string
    {
        if (null === $address) {
            return null;
        }

        if (1 !== preg_match('/\b(\d{5})\b/', $address, $matches)) {
            return null;
        }

        return $matches[1];
    }

    private function mapOrganizationType(DirectoryEstablishmentImportItem $item): ?OrganizationType
    {
        $importType = strtoupper($item->type);
        $normalizedName = $this->normalizeText($item->name);

        return match (true) {
            'EAJE' === $importType => OrganizationType::CRECHE,
            str_contains($normalizedName, 'mairie') || str_contains($normalizedName, 'ville de ') => OrganizationType::MAIRIE,
            str_contains($normalizedName, 'ram') => OrganizationType::RAM,
            str_contains($normalizedName, 'mam') => OrganizationType::MAM,
            str_contains($normalizedName, 'garderie') => OrganizationType::GARDERIE,
            str_contains($normalizedName, 'centre') => OrganizationType::CENTRE,
            default => null,
        };
    }

    private function normalizeText(?string $value): string
    {
        $value = null === $value ? '' : trim($value);

        if ('' === $value) {
            return '';
        }

        $asciiValue = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $asciiValue = false === $asciiValue ? $value : $asciiValue;
        $asciiValue = mb_strtolower($asciiValue);
        $asciiValue = preg_replace('/[^a-z0-9]+/', ' ', $asciiValue) ?? $asciiValue;

        return trim(preg_replace('/\s+/', ' ', $asciiValue) ?? $asciiValue);
    }
}
