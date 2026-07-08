<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use App\Application\Directory\DirectoryEstablishmentImportItem;
use App\Application\Directory\DirectoryEstablishmentMatch;
use App\Application\Directory\DirectoryEstablishmentMatcher;
use App\Application\Directory\DirectoryImportFileException;
use App\Application\Directory\DirectoryImportFileLoader;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Directory\DirectoryEstablishmentUpserter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:directory:import-establishments',
    description: 'Import establishments into the directory from a CAF JSON export.',
)]
final class ImportDirectoryEstablishmentsCommand extends Command
{
    public function __construct(
        private readonly DirectoryImportFileLoader $fileLoader,
        private readonly DirectoryEstablishmentMatcher $matcher,
        private readonly DirectoryEstablishmentUpserter $upserter,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument(description: 'JSON file path. Accepts an absolute path, /data/... or a path relative to data/imports/.')]
        string $file,
        #[Option(description: 'Import source identifier.')]
        string $source = 'caf',
        #[Option(description: 'Persist changes instead of running a dry-run.')]
        bool $apply = false,
        #[Option(description: 'Optional line offset for debugging or batch processing.')]
        int $offset = 0,
        #[Option(description: 'Optional line limit for debugging.')]
        ?int $limit = null,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $fileArgument = trim($file);
        $source = trim($source);
        $interactive = $input->isInteractive();
        $offset = max(0, $offset);
        $limit = null !== $limit ? max(1, $limit) : null;

        $organizationRank = 0;

        try {
            $items = $this->fileLoader->load($fileArgument, $offset, $limit);
        } catch (DirectoryImportFileException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
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
            'linkedByExternalId' => 0,
        ];

        foreach ($items as $index => $item) {
            ++$organizationRank;
            $violations = $this->validator->validate($item);

            if (0 < count($violations)) {
                ++$stats['validationErrors'];
                $io->warning(sprintf('Line %d ignored because validation failed: %s', $index + 1 + $offset, (string) $violations));

                continue;
            }

            $importLinkId = $this->matcher->findImportLinkIdByExternalId($source, $item);
            $organizationId = $this->matcher->findOrganizationIdLinkedByExternalIdentifiers($source, $item);
            $organization = null;

            if (null !== $organizationId) {
                $organization = $this->entityManager->getRepository(OrganizationEntity::class)->find($organizationId);
            }

            if ($organization instanceof OrganizationEntity) {
                ++$stats['linkedByExternalId'];
            }

            if (!$organization instanceof OrganizationEntity) {
                $organization = $this->resolveOrganization($io, $item, $organizationRank, $interactive, $stats);

                if (false === $organization) {
                    continue;
                }
            }

            if (!$organization instanceof OrganizationEntity) {
                $organization = new OrganizationEntity();
                ++$stats['createdOrganizations'];
            } else {
                ++$stats['updatedOrganizations'];
            }

            $this->upserter->hydrateOrganization($organization, $item);

            if ($apply) {
                $this->entityManager->persist($organization);
            }

            [$createdEmail, $reusedEmail] = $this->upserter->upsertEmailLink($organization->getContactDetails(), $item, $apply);
            $stats['createdEmails'] += (int) $createdEmail;
            $stats['reusedEmails'] += (int) $reusedEmail;

            [$createdPhone, $reusedPhone] = $this->upserter->upsertPhoneLink($organization->getContactDetails(), $item, $apply);
            $stats['createdPhones'] += (int) $createdPhone;
            $stats['reusedPhones'] += (int) $reusedPhone;

            $this->upserter->upsertAddressContact($organization->getContactDetails(), $item);

            if ($apply) {
                $this->upserter->persistImportLink($organization, $importLinkId, $item, $source);
                $this->entityManager->flush();
            }
        }

        $io->table(
            ['Metric', 'Count'],
            [
                ['Organizations created', (string) $stats['createdOrganizations']],
                ['Organizations updated', (string) $stats['updatedOrganizations']],
                ['Matched via external ids', (string) $stats['linkedByExternalId']],
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
     * @param array<string, int> $stats
     */
    private function resolveOrganization(
        SymfonyStyle $io,
        DirectoryEstablishmentImportItem $item,
        int $organizationRank,
        bool $interactive,
        array &$stats,
    ): false|OrganizationEntity|null {
        $candidates = $this->matcher->findOrganizationCandidates($item);

        if ([] === $candidates) {
            return null;
        }

        $topCandidate = $candidates[0];

        if ($topCandidate->score > $this->matcher->getAutoMatchScoreThreshold()) {
            return $this->entityManager->getRepository(OrganizationEntity::class)->find($topCandidate->organizationId);
        }

        ++$stats['ambiguous'];

        if (!$interactive) {
            ++$stats['ignored'];
            $io->note(sprintf(
                'Ambiguous match ignored for "%s" (best candidate: %s, score: %d%%). Run interactively to resolve it.',
                $item->name ?? $item->externalId,
                $topCandidate->organizationName,
                $topCandidate->score,
            ));

            return false;
        }

        $resolvedCandidate = $this->resolveInteractiveCandidate($io, $item, $organizationRank, $candidates);

        if (false === $resolvedCandidate) {
            ++$stats['ignored'];
        }

        return $resolvedCandidate;
    }

    /**
     * @param list<DirectoryEstablishmentMatch> $candidates
     */
    private function resolveInteractiveCandidate(SymfonyStyle $io, DirectoryEstablishmentImportItem $item, int $organizationRank, array $candidates): false|OrganizationEntity|null
    {
        $rows = [];
        $choices = ['new' => 'Créer un nouvel établissement', 'skip' => 'Ignorer cette ligne'];

        $io->definitionList(
            ['Nom import' => $item->name ?? '—'],
            ['Type import' => $item->type],
            ['Email import' => $item->emailAddress ?? '—'],
            ['Téléphone import' => $item->phoneNumber ?? '—'],
            ['Commune import' => $item->commune ?? '—'],
            ['Adresse import' => $item->address ?? '—'],
            ['Site import' => $item->websiteUrl ?? '—'],
        );

        foreach ($candidates as $candidate) {
            $choiceKey = (string) $candidate->organizationId;
            $choices[$choiceKey] = sprintf(
                'Lier à #%d %s (%d%%)',
                $candidate->organizationId,
                $candidate->organizationName,
                $candidate->score,
            );
            $rows[] = [
                $candidate->organizationId,
                $candidate->organizationName,
                $candidate->email,
                $candidate->phone,
                $candidate->commune,
                $candidate->score . '%',
            ];
        }

        $io->section(sprintf('[%d] Doute pour %s', $organizationRank, $item->name ?? $item->externalId));
        $io->table(['ID', 'Organisation', 'Email', 'Téléphone', 'Commune', 'Score'], $rows);

        foreach ($candidates as $candidate) {
            $io->definitionList(
                [sprintf('Candidat #%d', $candidate->organizationId) => $candidate->organizationName],
                ['Email base' => '' !== $candidate->email ? $candidate->email : '—'],
                ['Téléphone base' => '' !== $candidate->phone ? $candidate->phone : '—'],
                ['Commune base' => '' !== $candidate->commune ? $candidate->commune : '—'],
                ['Adresse base' => '' !== $candidate->address ? $candidate->address : '—'],
                ['Site base' => '' !== $candidate->website ? $candidate->website : '—'],
            );
        }

        $selection = $io->choice('Choisir comment résoudre cette ligne', array_values($choices));
        $selectedKey = array_search($selection, $choices, true);

        if ('new' === $selectedKey || false === $selectedKey) {
            return null;
        }

        if ('skip' === $selectedKey) {
            return false;
        }

        foreach ($candidates as $candidate) {
            if ((string) $candidate->organizationId === $selectedKey) {
                $organization = $this->entityManager->getRepository(OrganizationEntity::class)->find($candidate->organizationId);

                return $organization instanceof OrganizationEntity ? $organization : null;
            }
        }

        return null;
    }
}
