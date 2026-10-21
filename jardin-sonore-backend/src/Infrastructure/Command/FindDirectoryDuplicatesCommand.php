<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use App\Application\Directory\DirectoryDuplicateCandidateLookupInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:directory:find-duplicates',
    description: 'List possible duplicate organizations for manual review.',
)]
final class FindDirectoryDuplicatesCommand extends Command
{
    public function __construct(
        private readonly DirectoryDuplicateCandidateLookupInterface $candidateLookup,
    ) {
        parent::__construct();
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Maximum number of candidate pairs to display.')]
        int $limit = 100,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $candidates = $this->candidateLookup->findCandidates(max(1, $limit));

        if ([] === $candidates) {
            $io->success('Aucun candidat au dédoublonnage trouvé.');

            return Command::SUCCESS;
        }

        $signalLabels = [
            'same_name' => 'même nom',
            'same_address' => 'même adresse',
            'same_email' => 'même e-mail',
            'same_phone' => 'même téléphone',
        ];
        $rows = [];
        foreach ($candidates as $candidate) {
            $signals = array_map(
                static fn (string $signal): string => $signalLabels[$signal] ?? $signal,
                $candidate->signals,
            );
            $rows[] = [
                $candidate->firstOrganizationId,
                $candidate->firstOrganizationName,
                $candidate->firstLocation,
                $candidate->secondOrganizationId,
                $candidate->secondOrganizationName,
                $candidate->secondLocation,
                implode(', ', $signals),
            ];
        }

        $io->table(['ID', 'Établissement', 'Adresse', 'ID', 'Établissement', 'Adresse', 'Indices'], $rows);
        $io->note('Résultat à vérifier manuellement. Aucune fusion ni modification n’a été effectuée.');

        return Command::SUCCESS;
    }
}
