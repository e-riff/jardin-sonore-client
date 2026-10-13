<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\CommercialRequestEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use DomainException;
use Symfony\Component\Clock\ClockInterface;

final readonly class CommercialProjectWorkflow
{
    public function __construct(private ClockInterface $clock)
    {
    }

    public function qualify(CommercialRequestEntity $requestEntity, OrganizationEntity $organizationEntity, string $title): CommercialProjectEntity
    {
        if (CommercialRequestEntity::STATUS_WITHOUT_RESULT === $requestEntity->getStatus()) {
            throw new DomainException('A request without result cannot be qualified.');
        }

        $title = trim($title);
        if ('' === $title) {
            throw new DomainException('A project title is required.');
        }

        $now = $this->clock->now();
        $projectEntity = new CommercialProjectEntity($title, $organizationEntity, $requestEntity, $now);
        $requestEntity->addProject($projectEntity);
        $projectEntity->recordEvent('request_qualified', $now);

        return $projectEntity;
    }

    public function confirm(CommercialProjectEntity $projectEntity): void
    {
        if (CommercialProjectEntity::STATUS_CONFIRMED === $projectEntity->getStatus()) {
            return;
        }
        if (CommercialProjectEntity::STATUS_DISCUSSION !== $projectEntity->getStatus()) {
            throw new DomainException('Only a project in discussion can be confirmed.');
        }

        $now = $this->clock->now();
        $projectEntity->setStatus(CommercialProjectEntity::STATUS_CONFIRMED);
        $projectEntity->recordEvent('project_confirmed', $now);
    }

    public function closeWithoutResult(CommercialProjectEntity $projectEntity): void
    {
        if (CommercialProjectEntity::STATUS_DISCUSSION !== $projectEntity->getStatus()) {
            throw new DomainException('Only a project in discussion can be closed without result.');
        }
        $this->assertNoUnpaidInvoices($projectEntity);

        $now = $this->clock->now();
        foreach ($projectEntity->getActions() as $actionEntity) {
            if (CommercialActionEntity::STATUS_OPEN === $actionEntity->getStatus()) {
                $actionEntity->resolve(CommercialActionEntity::STATUS_CANCELED, $now);
                $projectEntity->recordEvent('action_canceled', $now, $actionEntity->getTitle())->linkAction($actionEntity);
            }
        }
        $projectEntity->setStatus(CommercialProjectEntity::STATUS_WITHOUT_RESULT, $now);
        $projectEntity->recordEvent('project_without_result', $now);
    }

    public function complete(CommercialProjectEntity $projectEntity): void
    {
        if (CommercialProjectEntity::STATUS_CONFIRMED !== $projectEntity->getStatus()) {
            throw new DomainException('Only a confirmed project can be completed.');
        }
        foreach ($projectEntity->getActions() as $actionEntity) {
            if (CommercialActionEntity::STATUS_OPEN === $actionEntity->getStatus()) {
                throw new DomainException('The project still has an open action.');
            }
        }
        $this->assertNoUnpaidInvoices($projectEntity);

        $now = $this->clock->now();
        $projectEntity->setStatus(CommercialProjectEntity::STATUS_COMPLETED, $now);
        $projectEntity->recordEvent('project_completed', $now);
    }

    public function changeStatusManually(CommercialProjectEntity $projectEntity, string $status): void
    {
        if (!in_array($status, [CommercialProjectEntity::STATUS_DISCUSSION, CommercialProjectEntity::STATUS_CONFIRMED, CommercialProjectEntity::STATUS_COMPLETED, CommercialProjectEntity::STATUS_WITHOUT_RESULT], true)) {
            throw new DomainException('Unknown project status.');
        }
        $previousStatus = $projectEntity->getStatus();
        if ($previousStatus === $status) {
            return;
        }
        if (CommercialProjectEntity::STATUS_COMPLETED === $status) {
            $this->complete($projectEntity);

            return;
        }
        if (CommercialProjectEntity::STATUS_CONFIRMED === $status && CommercialProjectEntity::STATUS_DISCUSSION === $previousStatus) {
            $this->confirm($projectEntity);

            return;
        }
        if (CommercialProjectEntity::STATUS_WITHOUT_RESULT === $status && CommercialProjectEntity::STATUS_DISCUSSION === $previousStatus) {
            $this->closeWithoutResult($projectEntity);

            return;
        }

        $now = $this->clock->now();
        if (CommercialProjectEntity::STATUS_WITHOUT_RESULT === $status) {
            $this->assertNoUnpaidInvoices($projectEntity);
            foreach ($projectEntity->getActions() as $actionEntity) {
                if (CommercialActionEntity::STATUS_OPEN === $actionEntity->getStatus()) {
                    $actionEntity->resolve(CommercialActionEntity::STATUS_CANCELED, $now);
                    $projectEntity->recordEvent('action_canceled', $now, $actionEntity->getTitle())->linkAction($actionEntity);
                }
            }
        }
        $projectEntity->setStatus($status, in_array($status, [CommercialProjectEntity::STATUS_WITHOUT_RESULT, CommercialProjectEntity::STATUS_COMPLETED], true) ? $now : null);
        $projectEntity->recordEvent('project_status_changed', $now, null, ['old_status' => $previousStatus, 'new_status' => $status]);
    }

    private function assertNoUnpaidInvoices(CommercialProjectEntity $projectEntity): void
    {
        foreach ($projectEntity->getInvoices() as $invoiceEntity) {
            if (null === $invoiceEntity->getPaidOn()) {
                throw new DomainException('The project still has an unpaid invoice.');
            }
        }
    }
}
