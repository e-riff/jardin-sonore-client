<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Application\Directory\DirectoryEstablishmentImportItem;
use App\Domain\Model\AddressBook\AddressContactType;
use App\Domain\Model\AddressBook\ContactDataSource;
use App\Domain\Model\AddressBook\CustomerStatus;
use App\Domain\Model\AddressBook\EmailContactType;
use App\Domain\Model\AddressBook\OrganizationType;
use App\Domain\Model\AddressBook\PhoneContactType;
use App\Infrastructure\Doctrine\Entity\AddressContactEntity;
use App\Infrastructure\Doctrine\Entity\ContactDetailsEntity;
use App\Infrastructure\Doctrine\Entity\DirectoryImportLinkEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactLinkEntity;
use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactLinkEntity;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:directory:import-establishments',
    description: 'Import establishments into the directory from a JSON export.',
)]
final class ImportDirectoryEstablishmentsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the JSON file to import.')
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Import source identifier.', 'directory')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Persist changes instead of running a dry-run.')
            ->addOption('interactive', null, InputOption::VALUE_NONE, 'Ask for confirmation when a match is ambiguous.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Optional line limit for debugging.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = (string) $input->getArgument('file');
        $source = trim((string) $input->getOption('source'));
        $apply = (bool) $input->getOption('apply');
        $interactive = (bool) $input->getOption('interactive');
        $limit = $input->getOption('limit');
        $limit = is_numeric($limit) ? max(1, (int) $limit) : null;

        if (!is_file($filePath) || !is_readable($filePath)) {
            $io->error("JSON file not readable: {$filePath}");

            return Command::FAILURE;
        }

        try {
            $payload = json_decode((string) file_get_contents($filePath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            $io->error("Invalid JSON: {$jsonException->getMessage()}");

            return Command::FAILURE;
        }

        if (!is_array($payload) || !is_array($payload['mainResults'] ?? null)) {
            $io->error('The JSON must contain a mainResults array.');

            return Command::FAILURE;
        }

        $items = array_map(
            static fn (array $row): DirectoryEstablishmentImportItem => DirectoryEstablishmentImportItem::fromArray($row),
            array_values(array_filter($payload['mainResults'], 'is_array')),
        );

        if (null !== $limit) {
            $items = array_slice($items, 0, $limit);
        }

        $stats = [
            'createdOrganizations' => 0,
            'updatedOrganizations' => 0,
            'createdEmails' => 0,
            'reusedEmails' => 0,
            'createdPhones' => 0,
            'reusedPhones' => 0,
            'ignored' => 0,
            'validationErrors' => 0,
            'ambiguous' => 0,
        ];

        $organizationCandidates = $this->entityManager->getRepository(OrganizationEntity::class)->findAll();

        foreach ($items as $index => $item) {
            $violations = $this->validator->validate($item);

            if (0 < count($violations)) {
                ++$stats['validationErrors'];
                $io->warning(sprintf('Line %d ignored because validation failed: %s', $index + 1, (string) $violations));

                continue;
            }

            $importLink = $this->entityManager->getRepository(DirectoryImportLinkEntity::class)->findOneBy([
                'source' => $source,
                'externalId' => $item->externalId,
            ]);

            $organization = $importLink instanceof DirectoryImportLinkEntity ? $importLink->getDirectoryEntry() : null;

            if (!$organization instanceof OrganizationEntity) {
                $candidates = $this->findOrganizationCandidates($organizationCandidates, $item);

                if ([] !== $candidates) {
                    $topCandidate = $candidates[0];

                    if (85 <= $topCandidate['score']) {
                        $organization = $topCandidate['organization'];
                    } elseif (55 <= $topCandidate['score']) {
                        ++$stats['ambiguous'];

                        if ($interactive) {
                            $resolvedCandidate = $this->resolveInteractiveCandidate($io, $item, $candidates);

                            if (false === $resolvedCandidate) {
                                ++$stats['ignored'];

                                continue;
                            }

                            $organization = $resolvedCandidate;
                        } else {
                            ++$stats['ignored'];
                            $io->note(sprintf(
                                'Ambiguous match ignored for "%s" (best candidate: %s, score: %d%%).',
                                $item->name ?? $item->externalId,
                                $topCandidate['organization']->getName(),
                                $topCandidate['score'],
                            ));

                            continue;
                        }
                    }
                }
            }

            if (!$organization instanceof OrganizationEntity) {
                $organization = new OrganizationEntity();
                $organizationCandidates[] = $organization;
                ++$stats['createdOrganizations'];
            } else {
                ++$stats['updatedOrganizations'];
            }

            $this->hydrateOrganization($organization, $item);

            if ($apply) {
                $this->entityManager->persist($organization);
            }

            [$createdEmail, $reusedEmail] = $this->upsertEmailLink($organization->getContactDetails(), $item, $apply);
            $stats['createdEmails'] += (int) $createdEmail;
            $stats['reusedEmails'] += (int) $reusedEmail;

            [$createdPhone, $reusedPhone] = $this->upsertPhoneLink($organization->getContactDetails(), $item, $apply);
            $stats['createdPhones'] += (int) $createdPhone;
            $stats['reusedPhones'] += (int) $reusedPhone;

            $this->upsertAddressContact($organization->getContactDetails(), $item);

            if ($apply) {
                $this->persistImportLink($organization, $importLink, $item, $source);
                $this->entityManager->flush();
            }
        }

        $io->table(
            ['Metric', 'Count'],
            [
                ['Organizations created', (string) $stats['createdOrganizations']],
                ['Organizations updated', (string) $stats['updatedOrganizations']],
                ['Emails created', (string) $stats['createdEmails']],
                ['Emails reused', (string) $stats['reusedEmails']],
                ['Phones created', (string) $stats['createdPhones']],
                ['Phones reused', (string) $stats['reusedPhones']],
                ['Ambiguous matches', (string) $stats['ambiguous']],
                ['Validation errors', (string) $stats['validationErrors']],
                ['Ignored', (string) $stats['ignored']],
            ],
        );

        if (!$apply) {
            $io->note('Dry-run completed. Re-run with --apply to persist changes.');
        } else {
            $io->success('Directory import completed.');
        }

        return Command::SUCCESS;
    }

    /**
     * @param list<OrganizationEntity> $organizationCandidates
     *
     * @return list<array{organization: OrganizationEntity, score: int, email: string, commune: string}>
     */
    private function findOrganizationCandidates(array $organizationCandidates, DirectoryEstablishmentImportItem $item): array
    {
        $matches = [];

        foreach ($organizationCandidates as $organizationCandidate) {
            $score = $this->computeCandidateScore($organizationCandidate, $item);

            if (35 > $score) {
                continue;
            }

            $matches[] = [
                'organization' => $organizationCandidate,
                'score' => $score,
                'email' => $this->firstOrganizationEmail($organizationCandidate),
                'commune' => $this->firstOrganizationCommune($organizationCandidate),
            ];
        }

        usort($matches, static fn (array $left, array $right): int => $right['score'] <=> $left['score']);

        return array_slice($matches, 0, 5);
    }

    private function computeCandidateScore(OrganizationEntity $organization, DirectoryEstablishmentImportItem $item): int
    {
        $score = 0;

        $importEmail = $this->normalizeEmail($item->emailAddress);
        $organizationEmail = $this->normalizeEmail($this->firstOrganizationEmail($organization));

        if (null !== $importEmail && null !== $organizationEmail && $importEmail === $organizationEmail) {
            $score += 55;
        }

        $nameSimilarity = $this->similarityPercentage($organization->getName(), $item->name);
        $score += (int) round($nameSimilarity * 0.35);

        $communeSimilarity = $this->similarityPercentage($this->firstOrganizationCommune($organization), $item->commune);
        $score += (int) round($communeSimilarity * 0.1);

        $addressSimilarity = $this->similarityPercentage($this->firstOrganizationAddress($organization), $item->address);
        $score += (int) round($addressSimilarity * 0.15);

        return min(100, $score);
    }

    /**
     * @param list<array{organization: OrganizationEntity, score: int, email: string, commune: string}> $candidates
     */
    private function resolveInteractiveCandidate(SymfonyStyle $io, DirectoryEstablishmentImportItem $item, array $candidates): false|OrganizationEntity|null
    {
        $rows = [];
        $choices = ['new' => 'Créer un nouvel établissement', 'skip' => 'Ignorer cette ligne'];

        foreach ($candidates as $candidate) {
            $organization = $candidate['organization'];
            $choiceKey = (string) $organization->getId();
            $choices[$choiceKey] = sprintf(
                'Lier à #%d %s (%d%%)',
                $organization->getId(),
                $organization->getName(),
                $candidate['score'],
            );
            $rows[] = [
                $organization->getId(),
                $organization->getName(),
                $candidate['email'],
                $candidate['commune'],
                $candidate['score'] . '%',
            ];
        }

        $io->section(sprintf('Ambiguity for %s', $item->name ?? $item->externalId));
        $io->table(['ID', 'Organization', 'Email', 'Commune', 'Score'], $rows);

        $selection = $io->choice('Choose how to resolve this row', array_values($choices));
        $selectedKey = array_search($selection, $choices, true);

        if ('new' === $selectedKey || false === $selectedKey) {
            return null;
        }

        if ('skip' === $selectedKey) {
            return false;
        }

        foreach ($candidates as $candidate) {
            if ((string) $candidate['organization']->getId() === $selectedKey) {
                return $candidate['organization'];
            }
        }

        return null;
    }

    private function hydrateOrganization(OrganizationEntity $organization, DirectoryEstablishmentImportItem $item): void
    {
        $organization->setName($item->name ?? $organization->getName());
        $organization->setWebsiteUrl($item->websiteUrl ?? $organization->getWebsiteUrl());

        if (null === $organization->getType() && 'EAJE' === strtoupper($item->type)) {
            $organization->setType(OrganizationType::CRECHE);
        }

        if (null === $organization->getCustomerStatus()) {
            $organization->setCustomerStatus(CustomerStatus::PROSPECT);
        }
    }

    /**
     * @return array{bool, bool}
     */
    private function upsertEmailLink(ContactDetailsEntity $contactDetails, DirectoryEstablishmentImportItem $item, bool $apply): array
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

        $emailContact = $this->entityManager->getRepository(EmailContactEntity::class)->findOneBy(['emailAddress' => $emailAddress]);
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
    private function upsertPhoneLink(ContactDetailsEntity $contactDetails, DirectoryEstablishmentImportItem $item, bool $apply): array
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

        $phoneContact = $this->entityManager->getRepository(PhoneContactEntity::class)->findOneBy(['phoneNumber' => $phoneNumber]);
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

    private function upsertAddressContact(ContactDetailsEntity $contactDetails, DirectoryEstablishmentImportItem $item): void
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

        $addressContact
            ->setAddress($item->address ?? $addressContact->getAddress())
            ->setCity($item->commune ?? $addressContact->getCity())
            ->setPostalCode($this->extractPostalCode($item->address) ?? $addressContact->getPostalCode())
            ->setMunicipality($this->findMunicipality($item));
    }

    private function persistImportLink(
        OrganizationEntity $organization,
        ?DirectoryImportLinkEntity $existingImportLink,
        DirectoryEstablishmentImportItem $item,
        string $source,
    ): void {
        $importLink = $existingImportLink ?? new DirectoryImportLinkEntity();
        $importLink
            ->setDirectoryEntry($organization)
            ->setSource($source)
            ->setExternalId($item->externalId)
            ->setExternalOrganizationId($item->externalOrganizationId)
            ->setPayloadHash(hash('sha256', json_encode($item->rawPayload, JSON_THROW_ON_ERROR)));

        $this->entityManager->persist($importLink);
    }

    private function findMunicipality(DirectoryEstablishmentImportItem $item): ?MunicipalityEntity
    {
        $postalCode = $this->extractPostalCode($item->address);
        $commune = $item->commune;

        if (null === $commune) {
            return null;
        }

        $criteria = ['name' => $commune];

        if (null !== $postalCode) {
            $criteria['postalCode'] = $postalCode;
        }

        $municipality = $this->entityManager->getRepository(MunicipalityEntity::class)->findOneBy($criteria);

        return $municipality instanceof MunicipalityEntity ? $municipality : null;
    }

    private function firstOrganizationEmail(OrganizationEntity $organization): string
    {
        foreach ($organization->getContactDetails()?->getEmailContactLinks() ?? [] as $emailContactLink) {
            $emailAddress = $emailContactLink->getEmailAddress();

            if (null !== $emailAddress && '' !== trim($emailAddress)) {
                return $emailAddress;
            }
        }

        return '';
    }

    private function firstOrganizationCommune(OrganizationEntity $organization): string
    {
        foreach ($organization->getContactDetails()?->getAddressContacts() ?? [] as $addressContact) {
            if (null !== $addressContact->getCity() && '' !== trim($addressContact->getCity())) {
                return $addressContact->getCity();
            }
        }

        return '';
    }

    private function firstOrganizationAddress(OrganizationEntity $organization): string
    {
        foreach ($organization->getContactDetails()?->getAddressContacts() ?? [] as $addressContact) {
            if (null !== $addressContact->getAddress() && '' !== trim($addressContact->getAddress())) {
                return $addressContact->getAddress();
            }
        }

        return '';
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

        $phoneNumber = preg_replace('/[^\d+]/', '', $phoneNumber) ?? '';

        return '' === $phoneNumber ? null : $phoneNumber;
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

    private function similarityPercentage(?string $left, ?string $right): int
    {
        $left = $this->normalizeText($left);
        $right = $this->normalizeText($right);

        if ('' === $left || '' === $right) {
            return 0;
        }

        similar_text($left, $right, $percentage);

        return (int) round($percentage);
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
}
