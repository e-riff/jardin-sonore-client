<?php

declare(strict_types=1);

namespace App\Infrastructure\Directory;

use App\Application\Directory\DirectoryEstablishmentImportItem;
use App\Application\Directory\DirectoryMunicipalityLookupInterface;
use App\Application\Directory\DirectorySharedContactLookupInterface;
use App\Domain\Model\AddressBook\AddressContactType;
use App\Domain\Model\AddressBook\ContactDataSource;
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

        $this->linkMunicipality($addressContact, $item->commune, $postalCode);
    }

    public function upsertAdditionalAddressContact(ContactDetailsEntity $contactDetails, string $address, ?string $commune): void
    {
        $address = trim($address);
        foreach ($contactDetails->getAddressContacts() as $existingAddressContact) {
            if (mb_strtolower(trim((string) $existingAddressContact->getAddress())) === mb_strtolower($address)) {
                return;
            }
        }

        $postalCode = $this->extractPostalCode($address);
        $addressContact = (new AddressContactEntity())
            ->setType(AddressContactType::OFFICE)
            ->setActive(true)
            ->setAddress($address)
            ->setPostalCode($postalCode)
            ->setCity($commune);
        $this->linkMunicipality($addressContact, $commune, $postalCode);
        $contactDetails->addAddressContact($addressContact);
    }

    private function linkMunicipality(AddressContactEntity $addressContact, ?string $commune, ?string $postalCode): void
    {
        $municipalityId = $this->directoryMunicipalityLookup->findIdByNameAndPostalCode(
            commune: $commune,
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
        $directOrganizationType = OrganizationType::tryFrom(mb_strtolower(trim($item->type)));
        if (null !== $directOrganizationType) {
            return $directOrganizationType;
        }

        $importType = strtoupper($item->type);
        $normalizedType = $this->normalizeText($item->type);
        $normalizedName = $this->normalizeText($item->name);

        return match (true) {
            'EAJE' === $importType => OrganizationType::CRECHE,
            in_array($importType, ['MJC', 'MPT'], true) => OrganizationType::MJC,
            str_contains($normalizedType, 'cafe') && str_contains($normalizedType, 'parent'),
            str_contains($normalizedName, 'cafe poussette'),
            str_contains($normalizedName, 'cafe des enfants') => OrganizationType::CAFE_PARENTS,
            str_contains($normalizedType, 'librairie jeunesse') => OrganizationType::LIBRAIRIE_JEUNESSE,
            str_contains($normalizedType, 'festival') || str_contains($normalizedType, 'evenement') => OrganizationType::FESTIVAL_EVENEMENT,
            str_contains($normalizedType, 'salle spectacle')
                || str_contains($normalizedType, 'salle de spectacle')
                || str_contains($normalizedType, 'theatre')
                || str_contains($normalizedType, 'scene nationale')
                || 'cdn' === $normalizedType
                || str_contains($normalizedType, 'equipement culturel') => OrganizationType::SALLE_SPECTACLE,
            str_contains($normalizedType, 'ludotheque'), str_contains($normalizedName, 'ludotheque') => OrganizationType::LUDOTHEQUE,
            str_contains($normalizedType, 'mediatheque') => OrganizationType::MEDIATHEQUE,
            in_array($normalizedType, ['centre', 'centre social', 'centre de loisirs', 'centre loisirs', 'accueil de loisirs', 'alsh'], true) => OrganizationType::CENTRE,
            in_array($normalizedType, ['laep', 'lieu de parentalite', 'lieux enfants parents'], true) => OrganizationType::LIEU_PARENTALITE,
            str_contains($normalizedName, 'mairie') || str_contains($normalizedName, 'ville de ') => OrganizationType::MAIRIE,
            in_array($normalizedType, ['rpe', 'ram', 'relais petite enfance', 'relais assistantes maternelles'], true),
            1 === preg_match('/\b(ram|rpe)\b/', $normalizedName),
            str_contains($normalizedName, 'relais petite enfance'),
            str_contains($normalizedName, 'relais assistantes maternelles') => OrganizationType::RPE,
            str_contains($normalizedName, 'mam') => OrganizationType::MAM,
            str_contains($normalizedName, 'garderie') => OrganizationType::GARDERIE,
            1 === preg_match('/\b(mjc|maison pour tous)\b/', $normalizedName) => OrganizationType::MJC,
            1 === preg_match('/\b(alsh|centre de loisirs|accueil de loisirs)\b/', $normalizedName) => OrganizationType::CENTRE,
            1 === preg_match('/\b(laep|lieu de parentalite)\b/', $normalizedName) => OrganizationType::LIEU_PARENTALITE,
            str_contains($normalizedName, 'centre social') => OrganizationType::CENTRE,
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
