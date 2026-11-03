<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use App\Application\Directory\CulturalPlaceWorkFile;
use App\Application\Directory\DirectoryEstablishmentImportItem;
use App\Application\Directory\DirectoryEstablishmentMatcher;
use RuntimeException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:directory:cultural-places-review', description: 'Examiner et mémoriser les rapprochements de lieux culturels.')]
final class ReviewCulturalPlacesCommand extends Command
{
    public function __construct(private readonly CulturalPlaceWorkFile $workFile, private readonly DirectoryEstablishmentMatcher $matcher)
    {
        parent::__construct();
    }

    public function __invoke(InputInterface $input, OutputInterface $output, #[Argument] string $file): int
    {
        $io = new SymfonyStyle($input, $output);
        $batch = $this->workFile->load($file);
        $unresolved = 0;
        foreach ($batch['rows'] as $index => $row) {
            if (!is_array($row) || !is_array($row['item'] ?? null)) {
                throw new RuntimeException('Ligne de travail invalide.');
            }
            if (null !== ($row['decision'] ?? null)) {
                continue;
            }
            $item = DirectoryEstablishmentImportItem::fromArray($row['item']);
            $linkedId = $this->matcher->findOrganizationIdLinkedByExternalIdentifiers('culture-lieux', $item);
            $candidates = null === $linkedId ? $this->matcher->findOrganizationCandidates($item) : [];
            if (null !== $linkedId) {
                $batch['rows'][$index]['decision'] = 'linked';
                $batch['rows'][$index]['organizationId'] = $linkedId;
                $batch['rows'][$index]['linkedBySource'] = true;
            } elseif ([] === $candidates) {
                $batch['rows'][$index]['decision'] = 'new';
                $batch['rows'][$index]['candidatesHash'] = hash('sha256', json_encode($candidates, JSON_THROW_ON_ERROR));
            } elseif (!$input->isInteractive()) {
                ++$unresolved;
                continue;
            } else {
                $choices = ['new' => 'Créer une fiche', 'skip' => 'Ignorer'];
                $io->section(sprintf('%d/%d — %s', $index + 1, count($batch['rows']), $item->name));
                foreach ($candidates as $candidate) {
                    $id = (string) $candidate->organizationId;
                    $choices[$id] = "Rattacher à la fiche #{$id} : {$candidate->organizationName}";
                    $io->table(['Champ', 'À importer', "Fiche #{$id} ({$candidate->score} %)"], [
                        ['Nom', $item->name, $candidate->organizationName],
                        ['Type', $item->type, $candidate->type?->value],
                        ['Commune', $item->commune, $candidate->commune],
                        ['Adresse', $item->address, $candidate->address],
                        ['Courriel', $item->emailAddress, $candidate->email],
                        ['Téléphone', $item->phoneNumber, $candidate->phone],
                        ['Site', $item->websiteUrl, $candidate->website],
                    ]);
                }
                $selection = $io->choice('Que faire ?', array_values($choices));
                $choice = array_search($selection, $choices, true);
                if (false === $choice) {
                    throw new RuntimeException('Choix invalide.');
                }
                if ('new' === $choice || 'skip' === $choice) {
                    $batch['rows'][$index]['decision'] = $choice;
                    if ('new' === $choice) {
                        $batch['rows'][$index]['candidatesHash'] = hash('sha256', json_encode($candidates, JSON_THROW_ON_ERROR));
                    }
                } else {
                    $batch['rows'][$index]['decision'] = 'linked';
                    $batch['rows'][$index]['organizationId'] = (int) $choice;
                    foreach ($candidates as $candidate) {
                        if ($candidate->organizationId === (int) $choice) {
                            $batch['rows'][$index]['candidateHash'] = hash('sha256', json_encode($candidate, JSON_THROW_ON_ERROR));
                            break;
                        }
                    }
                }
            }
            $this->workFile->save($file, $batch);
        }
        $summary = ['new' => 0, 'linked' => 0, 'skip' => 0];
        $newRows = [];
        foreach ($batch['rows'] as $row) {
            $decision = $row['decision'] ?? null;
            if (is_string($decision) && isset($summary[$decision])) {
                ++$summary[$decision];
            }
            if ('new' === $decision) {
                $newRows[] = [$row['item']['name'] ?? '', $row['item']['type'] ?? '', $row['item']['commune'] ?? '', $row['item']['mail'] ?? ''];
            }
        }
        if ([] !== $newRows) {
            $io->section('Fiches à créer');
            $io->table(['Nom', 'Type', 'Commune', 'Courriel'], $newRows);
        }
        $io->note(sprintf('Revue enregistrée : %d à créer, %d à relier, %d ignorés, %d à examiner.', $summary['new'], $summary['linked'], $summary['skip'], $unresolved));

        return Command::SUCCESS;
    }
}
