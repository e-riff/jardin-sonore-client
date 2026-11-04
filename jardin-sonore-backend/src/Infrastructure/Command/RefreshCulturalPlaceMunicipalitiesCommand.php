<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use App\Application\Directory\CulturalPlaceFetcher;
use App\Application\Directory\CulturalPlaceMunicipalityCodeMerger;
use App\Application\Directory\CulturalPlaceWorkFile;
use RuntimeException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:directory:cultural-places-refresh-municipalities', description: 'Ajouter les codes INSEE à une revue existante sans modifier ses choix.')]
final class RefreshCulturalPlaceMunicipalitiesCommand extends Command
{
    public function __construct(private readonly CulturalPlaceFetcher $fetcher, private readonly CulturalPlaceWorkFile $workFile, private readonly CulturalPlaceMunicipalityCodeMerger $merger)
    {
        parent::__construct();
    }

    public function __invoke(InputInterface $input, OutputInterface $output, #[Argument] string $file): int
    {
        $batch = $this->workFile->load($file);
        $department = $batch['department'] ?? null;
        if (!is_string($department)) {
            throw new RuntimeException('Département absent du fichier de travail.');
        }
        $sourceRows = $this->fetcher->fetch($department);
        $updated = $this->merger->merge($batch, $sourceRows);
        $this->workFile->save($file, $updated);
        $io = new SymfonyStyle($input, $output);
        $io->success('Codes INSEE ajoutés ; décisions conservées.');
        $io->text('Étape suivante :');
        $io->writeln('php bin/console app:directory:cultural-places-review ' . escapeshellarg($file), OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}
