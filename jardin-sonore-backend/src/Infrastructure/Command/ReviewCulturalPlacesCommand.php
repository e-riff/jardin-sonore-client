<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use App\Application\Directory\CulturalPlaceWorkFile;
use App\Application\Directory\DirectoryEstablishmentImportItem;
use App\Application\Directory\DirectoryEstablishmentMatch;
use App\Application\Directory\DirectoryEstablishmentMatcher;
use App\Application\Directory\DirectoryOrganizationTypeResolver;
use App\Infrastructure\Directory\CulturalPlaceCandidateFinder;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:directory:cultural-places-review', description: 'Examiner et mémoriser les rapprochements de lieux culturels.')]
final class ReviewCulturalPlacesCommand extends Command
{
    public function __construct(private readonly CulturalPlaceWorkFile $workFile, private readonly DirectoryEstablishmentMatcher $matcher, private readonly CulturalPlaceCandidateFinder $candidateFinder, private readonly DirectoryOrganizationTypeResolver $typeResolver)
    {
        parent::__construct();
    }

    public function __invoke(InputInterface $input, OutputInterface $output, #[Argument] string $file, #[Option(description: 'Revoir le numéro de ligne affiché précédemment.')] ?int $reconsider = null): int
    {
        $io = new SymfonyStyle($input, $output);
        $batch = $this->workFile->load($file);
        $reconsiderIndex = null;
        $indexes = array_keys($batch['rows']);
        if (null !== $reconsider) {
            $reconsiderIndex = $reconsider - 1;
            $row = $batch['rows'][$reconsiderIndex] ?? null;
            if (!$input->isInteractive() || 1 > $reconsider || !is_array($row) || !is_array($row['item'] ?? null)) {
                $io->error('Indique un numéro de ligne valide et lance la revue en mode interactif.');

                return Command::FAILURE;
            }
            if (true === ($row['applied'] ?? false) || null !== $this->matcher->findOrganizationIdLinkedByExternalIdentifiers('culture-lieux', DirectoryEstablishmentImportItem::fromArray($row['item']))) {
                $io->error('Cette ligne est déjà importée : sa liaison ne peut pas être modifiée ici.');

                return Command::FAILURE;
            }
            $batch['rows'][$reconsiderIndex]['decision'] = null;
            unset($batch['rows'][$reconsiderIndex]['organizationId'], $batch['rows'][$reconsiderIndex]['candidateHash'], $batch['rows'][$reconsiderIndex]['candidatesHash'], $batch['rows'][$reconsiderIndex]['linkedBySource']);
            $this->workFile->save($file, $batch);
            $indexes = [$reconsiderIndex, ...array_values(array_filter($indexes, static fn (int $index): bool => $index !== $reconsiderIndex))];
        }
        $unresolved = 0;
        foreach ($indexes as $index) {
            $row = $batch['rows'][$index];
            if (!is_array($row) || !is_array($row['item'] ?? null)) {
                throw new RuntimeException('Ligne de travail invalide.');
            }
            if (true === ($row['applied'] ?? false)) {
                continue;
            }
            $item = DirectoryEstablishmentImportItem::fromArray($row['item']);
            if ('new' === ($row['decision'] ?? null) && ($row['candidatesHash'] ?? null) === hash('sha256', '[]') && [] !== $this->candidateFinder->findCandidates($item)) {
                $batch['rows'][$index]['decision'] = null;
                $row['decision'] = null;
                $this->workFile->save($file, $batch);
            }
            if (null !== ($row['decision'] ?? null)) {
                continue;
            }
            $linkedId = $this->matcher->findOrganizationIdLinkedByExternalIdentifiers('culture-lieux', $item);
            $candidates = null === $linkedId ? $this->candidateFinder->findCandidates($item) : [];
            if (null !== $linkedId) {
                $batch['rows'][$index]['decision'] = 'linked';
                $batch['rows'][$index]['organizationId'] = $linkedId;
                $batch['rows'][$index]['linkedBySource'] = true;
            } elseif ([] === $candidates && $index !== $reconsiderIndex) {
                $batch['rows'][$index]['decision'] = 'new';
                $batch['rows'][$index]['candidatesHash'] = hash('sha256', json_encode($candidates, JSON_THROW_ON_ERROR));
            } elseif (!$input->isInteractive()) {
                ++$unresolved;
                continue;
            } else {
                $choices = ['0' => 'new', 'i' => 'skip'];
                $io->section(sprintf('%d/%d — %s', $index + 1, count($batch['rows']), $item->name));
                $displayCandidates = $candidates;
                $type = $this->typeResolver->resolve($item);
                if (null !== $type) {
                    usort($displayCandidates, static function (DirectoryEstablishmentMatch $a, DirectoryEstablishmentMatch $b) use ($type): int {
                        $rank = static fn (DirectoryEstablishmentMatch $match): int => $match->type === $type ? 0 : (null === $match->type ? 1 : 2);

                        return ($rank($a) <=> $rank($b)) ?: ($b->score <=> $a->score) ?: ($a->organizationId <=> $b->organizationId);
                    });
                }
                $headers = ['Champ', 'À importer'];
                $rows = [
                    ['Nom', $item->name],
                    ['Type', $item->type],
                    ['Commune', $item->commune],
                    ['Adresse', $item->address],
                    ['Courriel', $item->emailAddress],
                    ['Téléphone', $item->phoneNumber],
                    ['Site', $item->websiteUrl],
                ];
                foreach ($displayCandidates as $candidateIndex => $candidate) {
                    $id = (string) $candidate->organizationId;
                    $selectionNumber = (string) ($candidateIndex + 1);
                    $choices[$selectionNumber] = $id;
                    $headers[] = "[{$selectionNumber}] Fiche #{$id}";
                    foreach ([$candidate->organizationName, $candidate->type?->value, $candidate->commune, $candidate->address, $candidate->email, $candidate->phone, $candidate->website] as $fieldIndex => $value) {
                        $rows[$fieldIndex][] = $value;
                    }
                }
                $io->table($headers, $rows);
                $choice = $io->ask('Que faire ? (0 = créer, i = ignorer, numéro = rattacher)', null, static function (?string $answer) use ($choices): string {
                    $answer = mb_strtolower(trim($answer ?? ''));
                    if (!array_key_exists($answer, $choices)) {
                        throw new InvalidArgumentException('Choisis un numéro affiché, 0 ou i.');
                    }

                    return $choices[$answer];
                });
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

        $next = 0 === $unresolved ? 'apply' : 'review';
        $io->text('Étape suivante :');
        $io->writeln('php bin/console app:directory:cultural-places-' . $next . ' ' . escapeshellarg($file), OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}
