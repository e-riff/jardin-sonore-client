<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use App\Application\Directory\CulturalPlaceFetcher;
use App\Application\Directory\CulturalPlaceWorkFile;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:directory:cultural-places-prepare', description: 'Préparer un lot de lieux culturels par département.')]
final class PrepareCulturalPlacesCommand extends Command
{
    public function __construct(private readonly CulturalPlaceFetcher $fetcher, private readonly CulturalPlaceWorkFile $workFile)
    {
        parent::__construct();
    }

    public function __invoke(InputInterface $input, OutputInterface $output, #[Argument] string $department, #[Argument] string $file): int
    {
        $io = new SymfonyStyle($input, $output);
        if (file_exists($file)) {
            $io->error('Ce fichier existe déjà. Reprendre sa revue ou choisir un nouveau fichier.');

            return Command::FAILURE;
        }
        $items = $this->fetcher->fetch($department);
        $rows = array_map(static fn (array $item): array => [...$item, 'decision' => null], $items);
        $this->workFile->save($file, ['version' => 1, 'department' => strtoupper(trim($department)), 'rows' => $rows]);
        $io->success(sprintf('%d lieux préparés dans %s. Aucun établissement enregistré.', count($rows), $file));

        return Command::SUCCESS;
    }
}
