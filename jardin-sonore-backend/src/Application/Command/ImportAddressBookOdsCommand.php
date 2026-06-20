<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Infrastructure\Import\AddressBook\AddressBookOdsImporter;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:address-book:import-ods',
    description: 'Import the initial address book from the local ODS export.',
)]
final readonly class ImportAddressBookOdsCommand
{
    public function __construct(
        private AddressBookOdsImporter $addressBookOdsImporter,
        private KernelInterface $kernel,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'ODS file path. Defaults to ../.codex/Annuaire Caf.ods from the backend project directory.')]
        ?string $filePath = null,
        #[Option(description: 'Persist imported data. Without this option, the command only reports what would be imported.')]
        bool $apply = false,
    ): int {
        $filePath ??= $this->defaultFilePath();
        $dryRun = !$apply;

        try {
            $result = $this->addressBookOdsImporter->import($filePath, $dryRun);
        } catch (\Throwable $throwable) {
            $io->error($throwable->getMessage());

            return Command::FAILURE;
        }

        $io->title($dryRun ? 'Address book ODS import dry-run' : 'Address book ODS import');
        $io->definitionList(
            ['File' => $filePath],
            ['Rows read' => (string) $result->rowsRead],
            ['Rows ignored' => (string) $result->rowsIgnored],
            ['Organizations created' => (string) $result->organizationsCreated],
            ['Organizations updated' => (string) $result->organizationsUpdated],
            ['Addresses created' => (string) $result->addressesCreated],
            ['Addresses updated' => (string) $result->addressesUpdated],
            ['Emails created' => (string) $result->emailsCreated],
            ['Emails updated' => (string) $result->emailsUpdated],
            ['Emails ignored' => (string) $result->emailsIgnored],
            ['Phones created' => (string) $result->phonesCreated],
            ['Phones ignored' => (string) $result->phonesIgnored],
        );

        if ($result->hasErrors()) {
            $io->section('Errors');
            $io->listing(array_slice($result->errors(), 0, 50));

            if (count($result->errors()) > 50) {
                $io->note(sprintf('%d additional errors were hidden.', count($result->errors()) - 50));
            }
        }

        if ($dryRun) {
            $io->note('Dry-run only. Re-run with --apply to persist data.');
        }

        return $result->hasErrors() ? Command::FAILURE : Command::SUCCESS;
    }

    private function defaultFilePath(): string
    {
        $containerFilePath = '/workspace/.codex/Annuaire Caf.ods';

        if (is_file($containerFilePath)) {
            return $containerFilePath;
        }

        return $this->kernel->getProjectDir().'/../.codex/Annuaire Caf.ods';
    }
}
