<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\AddressBook;

use App\Domain\Model\AddressBook\AddressContactType;
use App\Domain\Model\AddressBook\ContactDataSource;
use App\Domain\Model\AddressBook\EmailContactType;
use App\Domain\Model\AddressBook\OrganizationSector;
use App\Domain\Model\AddressBook\OrganizationType;
use App\Domain\Model\AddressBook\PhoneContactType;
use App\Infrastructure\Doctrine\Entity\AddressContactEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;

final class AddressBookOdsImporter
{
    private const string SHEET_ESTABLISHMENTS = 'tousEtablissements';

    private const string SHEET_MEDIA_LIBRARIES = 'Mediathèques';

    /**
     * @var array<string, OrganizationEntity>
     */
    private array $organizationsByEmail = [];

    /**
     * @var array<string, OrganizationEntity>
     */
    private array $organizationsByFingerprint = [];

    /**
     * @var array<string, EmailContactEntity>
     */
    private array $emailContactsByAddress = [];

    /**
     * @var array<string, PhoneContactEntity>
     */
    private array $phoneContactsByNumber = [];

    /**
     * @var ObjectRepository<OrganizationEntity>
     */
    private ObjectRepository $organizationRepository;

    /**
     * @var ObjectRepository<EmailContactEntity>
     */
    private ObjectRepository $emailContactRepository;

    /**
     * @var ObjectRepository<PhoneContactEntity>
     */
    private ObjectRepository $phoneContactRepository;

    /**
     * @var ObjectRepository<MunicipalityEntity>
     */
    private ObjectRepository $municipalityRepository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OdsSpreadsheetReader $odsSpreadsheetReader,
    ) {
        $this->organizationRepository = $this->entityManager->getRepository(OrganizationEntity::class);
        $this->emailContactRepository = $this->entityManager->getRepository(EmailContactEntity::class);
        $this->phoneContactRepository = $this->entityManager->getRepository(PhoneContactEntity::class);
        $this->municipalityRepository = $this->entityManager->getRepository(MunicipalityEntity::class);
    }

    public function import(string $filePath, bool $dryRun): AddressBookOdsImportResult
    {
        $this->organizationsByEmail = [];
        $this->organizationsByFingerprint = [];
        $this->emailContactsByAddress = [];
        $this->phoneContactsByNumber = [];

        $result = new AddressBookOdsImportResult();
        $sheets = $this->odsSpreadsheetReader->readSheets($filePath, [self::SHEET_ESTABLISHMENTS, self::SHEET_MEDIA_LIBRARIES]);

        foreach ([self::SHEET_ESTABLISHMENTS, self::SHEET_MEDIA_LIBRARIES] as $sheetName) {
            if (!array_key_exists($sheetName, $sheets)) {
                $result->addError(sprintf('Sheet "%s" was not found.', $sheetName));

                continue;
            }

            $this->importSheet($sheetName, $sheets[$sheetName], $result, $dryRun);
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $result;
    }

    /**
     * @param list<list<string>> $rows
     */
    private function importSheet(string $sheetName, array $rows, AddressBookOdsImportResult $result, bool $dryRun): void
    {
        if ([] === $rows) {
            return;
        }

        $headers = $this->normalizeHeaders(array_shift($rows));

        foreach ($rows as $rowIndex => $row) {
            ++$result->rowsRead;

            try {
                $this->importRow($sheetName, $headers, $row, $rowIndex + 2, $result, $dryRun);
            } catch (\InvalidArgumentException $exception) {
                ++$result->rowsIgnored;
                $result->addError(sprintf('%s line %d: %s', $sheetName, $rowIndex + 2, $exception->getMessage()));
            }
        }
    }

    /**
     * @param array<string, int> $headers
     * @param list<string>       $row
     */
    private function importRow(string $sheetName, array $headers, array $row, int $lineNumber, AddressBookOdsImportResult $result, bool $dryRun): void
    {
        $name = $this->readOptionalCell($headers, $row, 'NOM');

        if ('' === $name) {
            ++$result->rowsIgnored;

            return;
        }

        $organizationType = $this->mapOrganizationType($this->readOptionalCell($headers, $row, 'TYPE'));
        $organizationSector = $this->mapOrganizationSector($this->readOptionalCell($headers, $row, 'SECTEUR'));
        $address = $this->emptyToNull($this->readOptionalCell($headers, $row, 'ADRESSE'));
        $postalCode = $this->normalizePostalCode($this->readOptionalCell($headers, $row, 'CP'));
        $city = $this->emptyToNull($this->readOptionalCell($headers, $row, 'VILLE'));
        $emails = $this->readEmailContacts($sheetName, $headers, $row, $lineNumber, $result);
        $phoneNumber = $this->normalizePhoneNumber($this->readOptionalCell($headers, $row, 'TELEPHONE'));

        if ([] === $emails && null === $phoneNumber && null === $address && null === $postalCode && null === $city) {
            ++$result->rowsIgnored;

            return;
        }

        $organizationEntity = $this->findOrganizationEntity($name, $postalCode, $city, $emails);
        $created = false;

        if (!$organizationEntity instanceof OrganizationEntity) {
            $organizationEntity = new OrganizationEntity();
            $organizationEntity->setName($name);
            $created = true;
        }

        $organizationEntity
            ->setType($organizationType)
            ->setSector($organizationSector);

        if ($created) {
            ++$result->organizationsCreated;
        } else {
            ++$result->organizationsUpdated;
        }

        $this->upsertAddressContact($organizationEntity, $address, $postalCode, $city, $result);

        foreach ($emails as $emailData) {
            $this->upsertEmailContact($organizationEntity, $emailData, $result);
        }

        if (null !== $phoneNumber) {
            $this->upsertPhoneContact($organizationEntity, $phoneNumber, $result);
        }

        $this->indexOrganization($organizationEntity, $postalCode, $city, $emails);

        if (!$dryRun) {
            $this->entityManager->persist($organizationEntity);
        }
    }

    /**
     * @param list<string> $headers
     *
     * @return array<string, int>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalizedHeaders = [];

        foreach ($headers as $index => $header) {
            $normalizedHeaders[trim($header)] = $index;
        }

        return $normalizedHeaders;
    }

    /**
     * @param array<string, int> $headers
     * @param list<string>       $row
     */
    private function readOptionalCell(array $headers, array $row, string $name): string
    {
        if (!array_key_exists($name, $headers)) {
            return '';
        }

        return trim($row[$headers[$name]] ?? '');
    }

    private function mapOrganizationType(string $value): OrganizationType
    {
        return match (mb_strtoupper(trim($value))) {
            'CRECHE' => OrganizationType::CRECHE,
            'MAIRIE' => OrganizationType::MAIRIE,
            'RAM' => OrganizationType::RAM,
            'MAM' => OrganizationType::MAM,
            'MEDIATHEQUE', 'MÉDIATHÈQUE' => OrganizationType::MEDIATHEQUE,
            'CENTRE' => OrganizationType::CENTRE,
            'GARDERIE' => OrganizationType::GARDERIE,
            default => OrganizationType::UNKNOWN,
        };
    }

    private function mapOrganizationSector(string $value): OrganizationSector
    {
        return match (mb_strtoupper(trim($value))) {
            'ASSOCIATION' => OrganizationSector::ASSOCIATION,
            'PUBLIC' => OrganizationSector::PUBLIC,
            'PRIVE', 'PRIVÉ', 'PRIVATE' => OrganizationSector::PRIVATE,
            default => OrganizationSector::UNKNOWN,
        };
    }

    /**
     * @param array<string, int> $headers
     * @param list<string>       $row
     *
     * @return list<array{emailAddress: string, optInNewsletter: bool, active: bool}>
     */
    private function readEmailContacts(
        string $sheetName,
        array $headers,
        array $row,
        int $lineNumber,
        AddressBookOdsImportResult $result,
    ): array {
        $emailColumns = self::SHEET_MEDIA_LIBRARIES === $sheetName ? ['MAIL1', 'MAIL2', 'MAIL3'] : ['MAIL1', 'MAIL2'];
        $emails = [];

        foreach ($emailColumns as $emailColumn) {
            $emailAddress = mb_strtolower($this->readOptionalCell($headers, $row, $emailColumn));

            if ('' === $emailAddress) {
                continue;
            }

            if (false === filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
                ++$result->emailsIgnored;
                $result->addError(sprintf('%s line %d: invalid email "%s".', $sheetName, $lineNumber, $emailAddress));

                continue;
            }

            $optInNewsletter = true;
            $active = true;

            if (self::SHEET_ESTABLISHMENTS === $sheetName) {
                $optInNewsletter = $this->readBooleanCell($headers, $row, 'accepteNewsletter', true, $emailColumn);
                $active = !$this->readBooleanCell($headers, $row, 'isDisabled', false, $emailColumn);
            }

            $emails[] = [
                'emailAddress' => $emailAddress,
                'optInNewsletter' => $optInNewsletter,
                'active' => $active,
            ];
        }

        return $emails;
    }

    /**
     * The export has repeated header labels. MAIL1 flags are the columns directly after MAIL1,
     * and MAIL2 flags are the columns directly after MAIL2.
     *
     * @param array<string, int> $headers
     * @param list<string>       $row
     */
    private function readBooleanCell(array $headers, array $row, string $headerName, bool $default, string $emailColumn): bool
    {
        if (!array_key_exists($emailColumn, $headers)) {
            return $default;
        }

        $startIndex = $headers[$emailColumn];
        $expectedOffset = 'accepteNewsletter' === $headerName ? 1 : 2;
        $value = trim($row[$startIndex + $expectedOffset] ?? '');

        if ('' === $value) {
            return $default;
        }

        return !in_array(mb_strtolower($value), ['0', 'false', 'non', 'no'], true);
    }

    /**
     * @param list<array{emailAddress: string, optInNewsletter: bool, active: bool}> $emails
     */
    private function findOrganizationEntity(string $name, ?string $postalCode, ?string $city, array $emails): ?OrganizationEntity
    {
        foreach ($emails as $emailData) {
            $emailAddress = $emailData['emailAddress'];

            if (array_key_exists($emailAddress, $this->organizationsByEmail)) {
                return $this->organizationsByEmail[$emailAddress];
            }

            $emailContactEntity = $this->findEmailContactEntity($emailAddress);
            $directoryEntryEntity = $emailContactEntity?->getContactDetails()?->getDirectoryEntry();

            if ($directoryEntryEntity instanceof OrganizationEntity) {
                return $directoryEntryEntity;
            }
        }

        $fingerprint = $this->buildOrganizationFingerprint($name, $postalCode, $city);

        if (array_key_exists($fingerprint, $this->organizationsByFingerprint)) {
            return $this->organizationsByFingerprint[$fingerprint];
        }

        if (null !== $postalCode || null !== $city) {
            $organizationEntity = $this->findOrganizationByNameAndAddress($name, $postalCode, $city);

            if ($organizationEntity instanceof OrganizationEntity) {
                return $organizationEntity;
            }
        }

        $organizationEntity = $this->organizationRepository->findOneBy(['name' => $name]);

        return $organizationEntity instanceof OrganizationEntity ? $organizationEntity : null;
    }

    private function findOrganizationByNameAndAddress(string $name, ?string $postalCode, ?string $city): ?OrganizationEntity
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('organizationEntity')
            ->from(OrganizationEntity::class, 'organizationEntity')
            ->leftJoin('organizationEntity.contactDetails', 'contactDetailsEntity')
            ->leftJoin('contactDetailsEntity.addressContacts', 'addressContactEntity')
            ->where('organizationEntity.name = :name')
            ->setParameter('name', $name)
            ->setMaxResults(1);

        if (null !== $postalCode) {
            $queryBuilder
                ->andWhere('addressContactEntity.postalCode = :postalCode')
                ->setParameter('postalCode', $postalCode);
        }

        if (null !== $city) {
            $queryBuilder
                ->andWhere('LOWER(addressContactEntity.city) = :city')
                ->setParameter('city', mb_strtolower($city));
        }

        $organizationEntity = $queryBuilder->getQuery()->getOneOrNullResult();

        return $organizationEntity instanceof OrganizationEntity ? $organizationEntity : null;
    }

    private function upsertAddressContact(
        OrganizationEntity $organizationEntity,
        ?string $address,
        ?string $postalCode,
        ?string $city,
        AddressBookOdsImportResult $result,
    ): void {
        if (null === $address && null === $postalCode && null === $city) {
            return;
        }

        $contactDetailsEntity = $organizationEntity->getContactDetails();

        if (null === $contactDetailsEntity) {
            return;
        }

        $addressContactEntity = $contactDetailsEntity->getAddressContacts()->first();

        if (!$addressContactEntity instanceof AddressContactEntity) {
            $addressContactEntity = new AddressContactEntity();
            $addressContactEntity->setType(AddressContactType::MAIN);
            $contactDetailsEntity->addAddressContact($addressContactEntity);
            $this->entityManager->persist($addressContactEntity);
            ++$result->addressesCreated;
        } else {
            ++$result->addressesUpdated;
        }

        $addressContactEntity
            ->setAddress($address)
            ->setPostalCode($postalCode)
            ->setCity($city)
            ->setMunicipality($this->findMunicipality($postalCode, $city));
    }

    /**
     * @param array{emailAddress: string, optInNewsletter: bool, active: bool} $emailData
     */
    private function upsertEmailContact(OrganizationEntity $organizationEntity, array $emailData, AddressBookOdsImportResult $result): void
    {
        $emailContactEntity = $this->findEmailContactEntity($emailData['emailAddress']);
        $contactDetailsEntity = $organizationEntity->getContactDetails();

        if (null === $contactDetailsEntity) {
            ++$result->emailsIgnored;

            return;
        }

        if (!$emailContactEntity instanceof EmailContactEntity) {
            $emailContactEntity = new EmailContactEntity();
            $emailContactEntity
                ->setEmailAddress($emailData['emailAddress'])
                ->setType(EmailContactType::MAIN);
            $contactDetailsEntity->addEmailContact($emailContactEntity);
            $this->entityManager->persist($emailContactEntity);
            $this->emailContactsByAddress[$emailData['emailAddress']] = $emailContactEntity;
            ++$result->emailsCreated;
        } else {
            $existingDirectoryEntryEntity = $emailContactEntity->getContactDetails()?->getDirectoryEntry();

            if (!$existingDirectoryEntryEntity instanceof OrganizationEntity || $existingDirectoryEntryEntity !== $organizationEntity) {
                ++$result->emailsIgnored;

                return;
            }

            ++$result->emailsUpdated;
        }

        $emailContactEntity
            ->setOptInNewsletter($emailData['optInNewsletter'])
            ->setActive($emailData['active'])
            ->setSource(ContactDataSource::LEGACY_IMPORT);
    }

    private function upsertPhoneContact(OrganizationEntity $organizationEntity, string $phoneNumber, AddressBookOdsImportResult $result): void
    {
        if ($this->findPhoneContactEntity($phoneNumber) instanceof PhoneContactEntity) {
            ++$result->phonesIgnored;

            return;
        }

        $contactDetailsEntity = $organizationEntity->getContactDetails();

        if (null === $contactDetailsEntity) {
            ++$result->phonesIgnored;

            return;
        }

        foreach ($contactDetailsEntity->getPhoneContacts() as $phoneContactEntity) {
            if ($phoneContactEntity->getPhoneNumber() === $phoneNumber) {
                ++$result->phonesIgnored;

                return;
            }
        }

        $phoneContactEntity = new PhoneContactEntity();
        $phoneContactEntity
            ->setType(PhoneContactType::MAIN)
            ->setPhoneNumber($phoneNumber);

        $contactDetailsEntity->addPhoneContact($phoneContactEntity);
        $this->entityManager->persist($phoneContactEntity);
        $this->phoneContactsByNumber[$phoneNumber] = $phoneContactEntity;
        ++$result->phonesCreated;
    }

    private function findEmailContactEntity(string $emailAddress): ?EmailContactEntity
    {
        if (array_key_exists($emailAddress, $this->emailContactsByAddress)) {
            return $this->emailContactsByAddress[$emailAddress];
        }

        $emailContactEntity = $this->emailContactRepository->findOneBy(['emailAddress' => $emailAddress]);

        if ($emailContactEntity instanceof EmailContactEntity) {
            $this->emailContactsByAddress[$emailAddress] = $emailContactEntity;

            return $emailContactEntity;
        }

        return null;
    }

    private function findPhoneContactEntity(string $phoneNumber): ?PhoneContactEntity
    {
        if (array_key_exists($phoneNumber, $this->phoneContactsByNumber)) {
            return $this->phoneContactsByNumber[$phoneNumber];
        }

        $phoneContactEntity = $this->phoneContactRepository->findOneBy(['phoneNumber' => $phoneNumber]);

        if ($phoneContactEntity instanceof PhoneContactEntity) {
            $this->phoneContactsByNumber[$phoneNumber] = $phoneContactEntity;

            return $phoneContactEntity;
        }

        return null;
    }

    private function findMunicipality(?string $postalCode, ?string $city): ?MunicipalityEntity
    {
        if (null === $postalCode && null === $city) {
            return null;
        }

        $criteria = [];

        if (null !== $postalCode) {
            $criteria['postalCode'] = $postalCode;
        }

        if (null !== $city) {
            $criteria['name'] = $city;
        }

        $municipalityEntity = $this->municipalityRepository->findOneBy($criteria);

        return $municipalityEntity instanceof MunicipalityEntity ? $municipalityEntity : null;
    }

    /**
     * @param list<array{emailAddress: string, optInNewsletter: bool, active: bool}> $emails
     */
    private function indexOrganization(OrganizationEntity $organizationEntity, ?string $postalCode, ?string $city, array $emails): void
    {
        foreach ($emails as $emailData) {
            $this->organizationsByEmail[$emailData['emailAddress']] = $organizationEntity;
        }

        $this->organizationsByFingerprint[$this->buildOrganizationFingerprint($organizationEntity->getName(), $postalCode, $city)] = $organizationEntity;
    }

    private function buildOrganizationFingerprint(string $name, ?string $postalCode, ?string $city): string
    {
        return implode('|', [
            mb_strtolower($name),
            $postalCode ?? '',
            null !== $city ? mb_strtolower($city) : '',
        ]);
    }

    private function normalizePostalCode(string $value): ?string
    {
        if (preg_match_all('/\d{5}/', $value, $matches) > 0) {
            return array_pop($matches[0]);
        }

        $value = preg_replace('/\D/', '', $value) ?? '';

        if ('' === $value) {
            return null;
        }

        return str_pad($value, 5, '0', STR_PAD_LEFT);
    }

    private function normalizePhoneNumber(string $value): ?string
    {
        $value = preg_replace('/\D/', '', $value) ?? '';

        return '' === $value ? null : $value;
    }

    private function emptyToNull(string $value): ?string
    {
        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
