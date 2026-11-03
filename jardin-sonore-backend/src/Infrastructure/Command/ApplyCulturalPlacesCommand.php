<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use App\Application\Directory\CulturalPlaceWorkFile;
use App\Application\Directory\DirectoryEstablishmentImportItem;
use App\Application\Directory\DirectoryEstablishmentMatcher;
use App\Application\Directory\DirectoryOrganizationTypeResolver;
use App\Infrastructure\Directory\DirectoryEstablishmentUpserter;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Repository\EmailContactDoctrineRepository;
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
        private readonly DirectoryEstablishmentUpserter $upserter,
        private readonly DirectoryOrganizationTypeResolver $typeResolver,
        private readonly EmailContactDoctrineRepository $emailRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    public function __invoke(InputInterface $input, OutputInterface $output, #[Argument] string $file): int
    {
        $io = new SymfonyStyle($input, $output);
        $batch = $this->workFile->load($file);
        foreach ($batch['rows'] as $row) {
            if (!is_array($row) || !is_array($row['item'] ?? null) || !in_array($row['decision'] ?? null, ['new', 'linked', 'skip'], true)) {
                $io->error('Terminez la revue de toutes les lignes avant l’application.');

                return Command::FAILURE;
            }
        }
        foreach ($batch['rows'] as $index => $row) {
            if ('skip' === $row['decision'] || true === ($row['applied'] ?? false)) {
                continue;
            }
            $item = DirectoryEstablishmentImportItem::fromArray($row['item']);
            if (0 < count($this->validator->validate($item)) || null === $item->name) {
                throw new RuntimeException("Données invalides pour {$item->externalId} ; import interrompu.");
            }
            $linkedId = $this->matcher->findOrganizationIdLinkedByExternalIdentifiers(self::SOURCE, $item);
            $result = 'linked';
            if (null !== $linkedId) {
                if ('linked' === $row['decision'] && $linkedId !== ($row['organizationId'] ?? null)) {
                    throw new RuntimeException("Le lien externe de {$item->externalId} a changé.");
                }
                $organization = $this->entityManager->find(OrganizationEntity::class, $linkedId);
                if ('new' === $row['decision']) {
                    $result = 'recovered';
                }
            } elseif ('new' === $row['decision']) {
                $candidatesHash = hash('sha256', json_encode($this->matcher->findOrganizationCandidates($item), JSON_THROW_ON_ERROR));
                $reviewedCandidatesHash = $row['candidatesHash'] ?? hash('sha256', '[]');
                if ($candidatesHash !== $reviewedCandidatesHash) {
                    $batch['rows'][$index]['decision'] = null;
                    $this->workFile->save($file, $batch);
                    throw new RuntimeException("Un rapprochement est apparu pour {$item->externalId} ; reprendre la revue.");
                }
                $organization = (new OrganizationEntity())->setName($item->name)->setType($this->typeResolver->resolve($item));
                $result = 'created';
            } else {
                if (true === ($row['linkedBySource'] ?? false)) {
                    $batch['rows'][$index]['decision'] = null;
                    $this->workFile->save($file, $batch);
                    throw new RuntimeException("Le lien externe de {$item->externalId} a disparu ; reprendre la revue.");
                }
                $organizationId = $row['organizationId'] ?? null;
                $organization = is_int($organizationId) ? $this->entityManager->find(OrganizationEntity::class, $organizationId) : null;
                if (isset($row['candidateHash'])) {
                    $currentHash = null;
                    foreach ($this->matcher->findOrganizationCandidates($item) as $candidate) {
                        if ($candidate->organizationId === $organizationId) {
                            $currentHash = hash('sha256', json_encode($candidate, JSON_THROW_ON_ERROR));
                        }
                    }
                    if ($currentHash !== $row['candidateHash']) {
                        $batch['rows'][$index]['decision'] = null;
                        $this->workFile->save($file, $batch);
                        throw new RuntimeException("La fiche choisie pour {$item->externalId} a changé ; reprendre la revue.");
                    }
                }
            }
            if (!$organization instanceof OrganizationEntity) {
                throw new RuntimeException("Fiche introuvable pour {$item->externalId}.");
            }
            $this->entityManager->wrapInTransaction(function () use ($organization, $item): void {
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
                $linkId = $this->matcher->findImportLinkIdByExternalId(self::SOURCE, $item);
                $this->upserter->persistImportLink($organization, $linkId, $item, self::SOURCE);
            });
            $batch['rows'][$index]['applied'] = true;
            $batch['rows'][$index]['result'] = $result;
            $batch['rows'][$index]['organizationId'] = $organization->getId();
            $this->workFile->save($file, $batch);
        }
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

        return Command::SUCCESS;
    }
}
