<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use App\Application\Directory\CulturalPlaceFetcher;
use App\Application\Directory\CulturalPlaceNormalizer;
use App\Application\Directory\CulturalPlaceWorkFile;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

#[AsCommand(name: 'app:directory:cultural-places-prepare', description: 'Préparer un lot de lieux culturels par département, avec un assistant interactif.')]
final class PrepareCulturalPlacesCommand extends Command
{
    public function __construct(
        private readonly CulturalPlaceFetcher $fetcher,
        private readonly CulturalPlaceWorkFile $workFile,
        #[Autowire('%kernel.project_dir%/data')]
        private readonly string $dataDirectory,
    ) {
        parent::__construct();
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Argument] ?string $department = null,
        #[Argument] ?string $file = null,
        #[Option(description: 'Catégories séparées par des virgules ; toutes par défaut hors assistant.')] ?string $types = null,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $guided = null === $department || null === $file;
        if (null === $department && !$input->isInteractive()) {
            $io->error('Indique un département en mode non interactif.');

            return Command::FAILURE;
        }
        $validateDepartment = static function (?string $answer): string {
            $answer = strtoupper(trim($answer ?? ''));
            if (1 !== preg_match('/^(?:\d{2}|2A|2B|97\d|98\d)$/', $answer)) {
                throw new InvalidArgumentException('Indique un code département : 42, 2A, 971…');
            }

            return $answer;
        };
        $department = null === $department ? $io->ask('Département', null, $validateDepartment) : $validateDepartment($department);
        $categories = CulturalPlaceNormalizer::CATEGORIES;
        $selected = null === $types ? array_keys($categories) : array_values(array_unique(array_map('trim', explode(',', $types))));
        if ([] !== array_diff($selected, array_keys($categories))) {
            $io->error('Catégories inconnues. Valeurs possibles : ' . implode(', ', array_keys($categories)));

            return Command::FAILURE;
        }
        if ($guided && $input->isInteractive()) {
            $io->section("Lieux culturels — département {$department}");
            $io->text('Derniers lots disponibles sur ce serveur ; « inconnue » signifie que la date n’a pas été enregistrée.');
            $io->table(['Catégorie', 'Dernière récupération', 'Lot / état'], $this->history($department));
            if (null === $types) {
                $labels = array_values($categories);
                $chosen = $io->choice('Types à récupérer (plusieurs numéros séparés par des virgules)', $labels, implode(',', array_keys($labels)), true);
                $selected = array_values(array_filter(array_keys($categories), static fn (string $key): bool => in_array($categories[$key], $chosen, true)));
            }
        }
        if (!is_dir($this->dataDirectory) && !mkdir($this->dataDirectory, 0770, true) && !is_dir($this->dataDirectory)) {
            throw new InvalidArgumentException('Impossible de créer le dossier des lots.');
        }
        $now = new DateTimeImmutable();
        $suggestedFile = $this->dataDirectory . '/lieux-culturels-' . $department . '-' . $now->format('Ymd-His-u') . '.json';
        $file ??= $guided && $input->isInteractive() ? $io->ask('Fichier à créer', $suggestedFile) : $suggestedFile;
        if (file_exists($file)) {
            $io->error('Ce fichier existe déjà. Reprendre sa revue ou choisir un nouveau fichier.');
            $io->writeln('php bin/console app:directory:cultural-places-review ' . escapeshellarg($file), OutputInterface::OUTPUT_RAW);

            return Command::FAILURE;
        }
        if (!is_dir(dirname($file)) || !is_writable(dirname($file))) {
            $io->error('Le dossier de destination doit exister et être accessible en écriture.');

            return Command::FAILURE;
        }
        $io->listing(array_map(static fn (string $key): string => $categories[$key], $selected));
        if ($guided && $input->isInteractive() && !$io->confirm('Récupérer ces lieux et créer le fichier ?', true)) {
            return Command::SUCCESS;
        }
        $items = $this->fetcher->fetch($department, categories: $selected);
        $rows = array_map(static fn (array $item): array => [...$item, 'decision' => null], $items);
        $this->workFile->save($file, ['version' => 2, 'department' => $department, 'categories' => $selected, 'preparedAt' => $now->format(DATE_ATOM), 'rows' => $rows]);
        $io->success(sprintf('%d lieux préparés dans %s. Aucun établissement enregistré.', count($rows), $file));
        $io->text('Étape suivante :');
        $io->writeln('php bin/console app:directory:cultural-places-review ' . escapeshellarg($file), OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }

    /** @return list<array{string, string, string}> */
    private function history(string $department): array
    {
        $latest = [];
        foreach (glob($this->dataDirectory . '/*.json') ?: [] as $path) {
            try {
                $batch = $this->workFile->load($path);
            } catch (Throwable) {
                continue;
            }
            if ($department !== $batch['department']) {
                continue;
            }
            $batchCategories = $batch['categories'] ?? array_unique(array_column(array_column($batch['rows'], 'item'), 'type'));
            $date = is_string($batch['preparedAt'] ?? null) ? $batch['preparedAt'] : '';
            $remaining = count(array_filter($batch['rows'], static fn (array $row): bool => true !== ($row['applied'] ?? false) && 'skip' !== ($row['decision'] ?? null)));
            foreach ($batchCategories as $category) {
                if (!isset($latest[$category]) || 0 < strcmp($date, $latest[$category][0])) {
                    $latest[$category] = [$date, basename($path) . ' — ' . (0 === $remaining ? ('appliqué le ' . ($batch['appliedAt'] ?? 'date inconnue')) : "{$remaining} ligne(s) restante(s)")];
                }
            }
        }
        $rows = [];
        foreach (CulturalPlaceNormalizer::CATEGORIES as $key => $label) {
            $rows[] = [$label, ($latest[$key][0] ?? '') ?: 'inconnue', $latest[$key][1] ?? 'aucun lot'];
        }

        return $rows;
    }
}
