<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use App\Application\Directory\CulturalPlaceWorkFile;
use App\Application\Directory\DirectoryEstablishmentImportItem;
use App\Application\Directory\DirectoryEstablishmentMatcher;
use App\Application\Directory\DirectoryOrganizationTypeResolver;
use App\Infrastructure\Directory\CulturalPlaceCandidateFinder;
use App\Infrastructure\Directory\DirectoryEstablishmentUpserter;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Repository\EmailContactDoctrineRepository;
use App\Infrastructure\Doctrine\Repository\MunicipalityDoctrineRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'app:directory:cultural-places-apply', description: 'Appliquer les décisions validées d’un lot de lieux culturels.')]
final class ApplyCulturalPlacesCommand extends Command
{
    private const string SOURCE = 'culture-lieux';

    public function __construct(
        private readonly CulturalPlaceWorkFile $workFile,
        private readonly DirectoryEstablishmentMatcher $matcher,
        private readonly CulturalPlaceCandidateFinder $candidateFinder,
        private readonly DirectoryEstablishmentUpserter $upserter,
        private readonly DirectoryOrganizationTypeResolver $typeResolver,
        private readonly EmailContactDoctrineRepository $emailRepository,
        private readonly MunicipalityDoctrineRepository $municipalityRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    public function __invoke(InputInterface $input, OutputInterface $output, #[Argument] string $file): int
    {
        $io = new SymfonyStyle($input, $output);
        $batch = $this->workFile->load($file);
        $source = $batch['source'] ?? self::SOURCE;
        if (!is_string($source) || '' === trim($source)) {
            throw new RuntimeException('Source du lot invalide.');
        }
        foreach ($batch['rows'] as $row) {
            if (!is_array($row) || !is_array($row['item'] ?? null) || !in_array($row['decision'] ?? null, ['new', 'linked', 'skip'], true)) {
                $io->error('Terminez la revue de toutes les lignes avant l’application.');
                $io->writeln('php bin/console app:directory:cultural-places-review ' . escapeshellarg($file), OutputInterface::OUTPUT_RAW);

                return Command::FAILURE;
            }
            if ('skip' !== $row['decision'] && null !== ($row['item']['commune'] ?? null)) {
                $code = $row['item']['codeCommune'] ?? null;
                if (!is_string($code) || 1 !== preg_match('/^(?:\d{2}|2A|2B)\d{3}$/', $code)) {
                    $io->error('Code INSEE absent ou invalide. Actualisez les communes du fichier avant l’import.');

                    return Command::FAILURE;
                }
                if (null === $this->municipalityRepository->findOneBy(['inseeCode' => $code])) {
                    $io->error("Commune inconnue dans l’annuaire : code INSEE {$code}. Aucune ligne n’a été importée.");

                    return Command::FAILURE;
                }
            }
        }
        $staleRows = [];
        foreach ($batch['rows'] as $index => $row) {
            if ('skip' === $row['decision'] || true === ($row['applied'] ?? false)) {
                continue;
            }
            $item = DirectoryEstablishmentImportItem::fromArray($row['item']);
            $linkedId = $this->matcher->findOrganizationIdLinkedByExternalIdentifiers($source, $item);
            $staleMessage = null;
            if (null !== $linkedId && 'linked' === $row['decision'] && $linkedId !== ($row['organizationId'] ?? null)) {
                throw new RuntimeException("Le lien externe de {$item->externalId} a changé.");
            }
            if (null === $linkedId && 'new' === $row['decision']) {
                $candidatesHash = hash('sha256', json_encode($this->candidateFinder->findCandidates($item), JSON_THROW_ON_ERROR));
                $reviewedCandidatesHash = $row['candidatesHash'] ?? hash('sha256', '[]');
                if ($candidatesHash !== $reviewedCandidatesHash) {
                    $staleMessage = "Un rapprochement est apparu pour {$item->externalId}";
                }
            } elseif (null === $linkedId && true === ($row['linkedBySource'] ?? false)) {
                $staleMessage = "Le lien externe de {$item->externalId} a disparu";
            } elseif (null === $linkedId && 'linked' === $row['decision'] && isset($row['candidateHash'])) {
                $organizationId = $row['organizationId'] ?? null;
                $currentHash = null;
                foreach ($this->candidateFinder->findCandidates($item) as $candidate) {
                    if ($candidate->organizationId === $organizationId) {
                        $currentHash = hash('sha256', json_encode($candidate, JSON_THROW_ON_ERROR));
                    }
                }
                if ($currentHash !== $row['candidateHash']) {
                    $staleMessage = "La fiche choisie pour {$item->externalId} a changé";
                }
            }
            if (null !== $staleMessage) {
                $batch['rows'][$index]['decision'] = null;
                $staleRows[] = $staleMessage;
            }
        }
        if ([] !== $staleRows) {
            $this->workFile->save($file, $batch);
            throw new RuntimeException(implode("\n", $staleRows) . '. Reprenez la revue. Aucune nouvelle fiche n’a été écrite. Commande : php bin/console app:directory:cultural-places-review ' . escapeshellarg($file));
        }
        foreach ($batch['rows'] as $index => $row) {
            if ('skip' === $row['decision'] || true === ($row['applied'] ?? false)) {
                continue;
            }
            $item = DirectoryEstablishmentImportItem::fromArray($row['item']);
            if (0 < count($this->validator->validate($item)) || null === $item->name) {
                throw new RuntimeException("Données invalides pour {$item->externalId} ; import interrompu.");
            }
            $linkedId = $this->matcher->findOrganizationIdLinkedByExternalIdentifiers($source, $item);
            $result = 'linked';
            if (null !== $linkedId) {
                $organization = $this->entityManager->find(OrganizationEntity::class, $linkedId);
                if ('new' === $row['decision']) {
                    $result = 'recovered';
                }
            } elseif ('new' === $row['decision']) {
                $organization = (new OrganizationEntity())->setName($item->name)->setType($this->typeResolver->resolve($item));
                $result = 'created';
            } else {
                $organizationId = $row['organizationId'] ?? null;
                $organization = is_int($organizationId) ? $this->entityManager->find(OrganizationEntity::class, $organizationId) : null;
            }
            if (!$organization instanceof OrganizationEntity) {
                throw new RuntimeException("Fiche introuvable pour {$item->externalId}.");
            }
            $this->entityManager->wrapInTransaction(function () use ($organization, $item, $source): void {
                $this->entityManager->persist($organization);
                if (null === $organization->getWebsiteUrl() && null !== $item->websiteUrl) {
                    $organization->setWebsiteUrl($item->websiteUrl);
                }
                if (null === $organization->getType()) {
                    $organization->setType($this->typeResolver->resolve($item));
                }
                foreach (array_filter([$item->emailAddress, ...$item->additionalEmails]) as $email) {
                    $existingEmail = $this->emailRepository->findEntityByEmailAddress($email);
                    if (null !== $existingEmail && (!$existingEmail->isActive() || $existingEmail->isUnsubscribed())) {
                        continue;
                    }
                    $emailItem = DirectoryEstablishmentImportItem::fromArray([...$item->rawPayload, 'mail' => $email]);
                    $this->upserter->upsertEmailLink($organization->getContactDetails(), $emailItem, true);
                }
                foreach (array_filter([$item->phoneNumber, ...$item->additionalPhones]) as $phone) {
                    $phoneItem = DirectoryEstablishmentImportItem::fromArray([...$item->rawPayload, 'phone' => $phone]);
                    $this->upserter->upsertPhoneLink($organization->getContactDetails(), $phoneItem, true);
                }
                if ($organization->getContactDetails()->getAddressContacts()->isEmpty()) {
                    $this->upserter->upsertAddressContact($organization->getContactDetails(), $item);
                }
                $linkId = $this->matcher->findImportLinkIdByExternalId($source, $item);
                $this->upserter->persistImportLink($organization, $linkId, $item, $source);
            });
            $batch['rows'][$index]['applied'] = true;
            $batch['rows'][$index]['result'] = $result;
            $batch['rows'][$index]['organizationId'] = $organization->getId();
            $this->workFile->save($file, $batch);
        }
        $batch['appliedAt'] ??= (new DateTimeImmutable())->format(DATE_ATOM);
        $this->workFile->save($file, $batch);
        $summaryRows = [];
        foreach ($batch['rows'] as $row) {
            $resultLabel = match ($row['result'] ?? null) {
                'created' => 'Créée',
                'linked' => 'Reliée',
                'recovered' => 'Lien retrouvé après reprise',
                default => 'Ignorée',
            };
            $summaryRows[] = [$resultLabel, $row['item']['name'] ?? '', $row['item']['id'] ?? '', $row['organizationId'] ?? '—'];
        }
        $io->table(['Résultat', 'Lieu', 'Identifiant source', 'Fiche annuaire'], $summaryRows);
        $io->success('Lot appliqué. Les décisions sont conservées dans le fichier.');
        $io->text('Pour préparer un nouveau lot :');
        $io->writeln('php bin/console app:directory:cultural-places-prepare', OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}
