<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInvoiceEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use DateTimeImmutable;
use DomainException;
use Symfony\Component\Clock\ClockInterface;

final readonly class RecordCommercialInvoice
{
    public function __construct(
        private CommercialActionWorkflow $actionWorkflow,
        private ClockInterface $clock,
    ) {
    }

    public function issue(CommercialProjectEntity $projectEntity, string $reference, int $amountCents, DateTimeImmutable $issuedOn): CommercialInvoiceEntity
    {
        $reference = trim($reference);
        if ('' === $reference || 0 > $amountCents) {
            throw new DomainException('An invoice needs a reference and non-negative amount.');
        }
        if (CommercialProjectEntity::STATUS_CONFIRMED !== $projectEntity->getStatus()) {
            throw new DomainException('Only a confirmed project can receive an invoice.');
        }

        $invoiceEntity = new CommercialInvoiceEntity($projectEntity, $reference, $amountCents, $issuedOn);
        $projectEntity->addInvoice($invoiceEntity);
        $projectEntity->recordEvent('invoice_issued', $this->clock->now(), $reference);
        $this->createReminderIfOverdue($invoiceEntity);

        return $invoiceEntity;
    }

    public function createReminderIfOverdue(CommercialInvoiceEntity $invoiceEntity): bool
    {
        if (null !== $invoiceEntity->getPaidOn() || null !== $invoiceEntity->getReminderAction()) {
            return false;
        }
        $dueOn = $invoiceEntity->getIssuedOn()->modify('+30 days');
        if ($dueOn->format('Y-m-d') > $this->clock->now()->format('Y-m-d')) {
            return false;
        }

        $projectEntity = $invoiceEntity->getProject();
        $actionEntity = new CommercialActionEntity($projectEntity, "Relancer la facture {$invoiceEntity->getReference()}", $dueOn, null, $projectEntity->getPrimaryContact());
        $projectEntity->addAction($actionEntity);
        $invoiceEntity->setReminderAction($actionEntity);
        $projectEntity->recordEvent('invoice_reminder_created', $this->clock->now(), $invoiceEntity->getReference());

        return true;
    }

    public function markPaid(CommercialInvoiceEntity $invoiceEntity, DateTimeImmutable $paidOn): void
    {
        if (null !== $invoiceEntity->getPaidOn()) {
            return;
        }
        $invoiceEntity->markPaid($paidOn);
        $reminderActionEntity = $invoiceEntity->getReminderAction();
        if ($reminderActionEntity instanceof CommercialActionEntity && CommercialActionEntity::STATUS_OPEN === $reminderActionEntity->getStatus()) {
            $this->actionWorkflow->cancel($reminderActionEntity);
        }
        $invoiceEntity->getProject()->recordEvent('invoice_paid', $this->clock->now(), $invoiceEntity->getReference());
    }
}
