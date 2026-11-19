<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Commercial;

use App\Application\Commercial\CommercialAmountParser;
use App\Application\Commercial\RecordCommercialInvoice;
use App\Application\Mcp\ProjectInvoiceManager;
use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInvoiceEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInvoicePreparationEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;

final readonly class DoctrineProjectInvoiceManager implements ProjectInvoiceManager
{
    public function __construct(private EntityManagerInterface $entityManager, private RecordCommercialInvoice $recorder)
    {
    }

    public function list(int $projectId): array
    {
        $projectEntity = $this->project($projectId);
        $preparations = $this->entityManager->getRepository(CommercialInvoicePreparationEntity::class)->findBy(['project' => $projectEntity]);

        return [
            'todo' => array_values(array_map(static fn (CommercialInvoicePreparationEntity $preparationEntity): array => [
                'serviceMonth' => $preparationEntity->getServiceMonth()->format('Y-m'),
                'dueOn' => $preparationEntity->getAction()->getDueOn()->format('Y-m-d'),
                'actionId' => $preparationEntity->getAction()->getId(),
            ], array_filter($preparations, static fn (CommercialInvoicePreparationEntity $preparationEntity): bool => CommercialActionEntity::STATUS_OPEN === $preparationEntity->getAction()->getStatus()))),
            'sent' => array_map(static fn (CommercialInvoiceEntity $invoiceEntity): array => [
                'id' => $invoiceEntity->getId(), 'reference' => $invoiceEntity->getReference(),
                'amountCents' => $invoiceEntity->getAmountCents(), 'description' => $invoiceEntity->getDescription(),
                'serviceMonth' => $invoiceEntity->getServiceMonth()->format('Y-m'), 'issuedOn' => $invoiceEntity->getIssuedOn()->format('Y-m-d'),
            ], $projectEntity->getInvoices()->toArray()),
        ];
    }

    public function record(array $arguments): array
    {
        try {
            return $this->entityManager->wrapInTransaction(fn (): array => $this->recordLocked($arguments));
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('This invoice reference is already used.');
        }
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function recordLocked(array $arguments): array
    {
        $projectId = $arguments['projectId'] ?? null;
        $reference = $arguments['reference'] ?? null;
        $amount = $arguments['amount'] ?? null;
        $description = $arguments['description'] ?? null;
        $sentOn = $arguments['sentOn'] ?? null;
        $serviceMonth = $arguments['serviceMonth'] ?? null;
        if (!is_int($projectId) || 1 > $projectId || !is_string($reference) || '' === trim($reference) || 64 < mb_strlen($reference)
            || !is_string($description) || '' === trim($description) || 255 < mb_strlen($description) || !is_string($amount)
            || !is_string($sentOn) || !is_string($serviceMonth) || !preg_match('/^\\d{4}-\\d{2}$/', $serviceMonth)) {
            throw new DomainException('Invalid invoice parameters.');
        }
        $amountCents = CommercialAmountParser::euroCents($amount);
        $issuedOn = DateTimeImmutable::createFromFormat('!Y-m-d', $sentOn);
        $month = DateTimeImmutable::createFromFormat('!Y-m-d', $serviceMonth . '-01');
        if (null === $amountCents || !$issuedOn || $issuedOn->format('Y-m-d') !== $sentOn || !$month || $month->format('Y-m') !== $serviceMonth) {
            throw new DomainException('Invalid invoice amount or date.');
        }
        $projectEntity = $this->entityManager->find(CommercialProjectEntity::class, $projectId, LockMode::PESSIMISTIC_WRITE);
        if (!$projectEntity instanceof CommercialProjectEntity) {
            throw new DomainException('Project not found.');
        }
        $reference = trim($reference);
        $existingInvoiceEntity = $this->entityManager->getRepository(CommercialInvoiceEntity::class)->findOneBy(['reference' => $reference]);
        if ($existingInvoiceEntity instanceof CommercialInvoiceEntity) {
            if ($existingInvoiceEntity->getProject() === $projectEntity && !$existingInvoiceEntity->hasPaymentTracking() && $existingInvoiceEntity->getAmountCents() === $amountCents && $existingInvoiceEntity->getDescription() === trim($description) && $existingInvoiceEntity->getIssuedOn()->format('Y-m-d') === $sentOn && $existingInvoiceEntity->getServiceMonth()->format('Y-m') === $serviceMonth) {
                return ['id' => $existingInvoiceEntity->getId(), 'reference' => $reference, 'status' => 'sent'];
            }
            throw new DomainException('This invoice reference is already used.');
        }
        $invoiceEntity = $this->recorder->issueSimple($projectEntity, $reference, $amountCents, $issuedOn, $month, $description);
        $preparationEntity = $this->entityManager->getRepository(CommercialInvoicePreparationEntity::class)->findOneBy(['project' => $projectEntity, 'serviceMonth' => $month]);
        if ($preparationEntity instanceof CommercialInvoicePreparationEntity && CommercialActionEntity::STATUS_OPEN === $preparationEntity->getAction()->getStatus()) {
            $preparationEntity->getAction()->resolve(CommercialActionEntity::STATUS_DONE, new DateTimeImmutable());
        }
        $this->entityManager->flush();

        return ['id' => $invoiceEntity->getId(), 'reference' => $reference, 'status' => 'sent'];
    }

    private function project(int $projectId): CommercialProjectEntity
    {
        $projectEntity = $this->entityManager->find(CommercialProjectEntity::class, $projectId);
        if (!$projectEntity instanceof CommercialProjectEntity) {
            throw new DomainException('Project not found.');
        }

        return $projectEntity;
    }
}
