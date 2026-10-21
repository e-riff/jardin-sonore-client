<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Commercial;

use App\Application\Commercial\CalendarEventDraft;
use App\Application\Commercial\CommercialActionWorkflow;
use App\Application\Commercial\CommercialAppointmentManager;
use App\Application\Commercial\RefreshCommercialAppointments;
use App\Application\Mcp\ProjectScheduleManager;
use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialAppointmentEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class DoctrineProjectScheduleManager implements ProjectScheduleManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CommercialActionWorkflow $actionWorkflow,
        private CommercialAppointmentManager $appointmentManager,
        private RefreshCommercialAppointments $appointmentRefresher,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /** @return array<string, mixed> */
    public function getAppointment(int $projectId, int $appointmentId): array
    {
        $this->appointmentRefresher->run($projectId);

        return $this->appointmentResult($this->appointment($projectId, $appointmentId));
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function execute(string $operation, array $arguments): array
    {
        $projectId = $this->positiveId($arguments, 'projectId');
        $projectEntity = $this->project($projectId);

        return match ($operation) {
            'create_project_task' => $this->createTask($projectEntity, $arguments),
            'update_project_task' => $this->updateTask($projectId, $arguments),
            'complete_project_task' => $this->completeTask($projectId, $arguments),
            'create_project_appointment' => $this->createAppointment($projectEntity, $arguments),
            'update_project_appointment' => $this->updateAppointment($projectId, $arguments),
            'cancel_project_appointment' => $this->cancelAppointment($projectId, $arguments),
            'set_project_appointment_summary' => $this->setAppointmentSummary($projectId, $arguments),
            default => throw new DomainException('Unknown schedule operation.'),
        };
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function createTask(CommercialProjectEntity $projectEntity, array $arguments): array
    {
        if (!in_array($projectEntity->getStatus(), [CommercialProjectEntity::STATUS_DISCUSSION, CommercialProjectEntity::STATUS_CONFIRMED], true)) {
            throw new DomainException('Closed dossiers cannot receive tasks.');
        }
        $actionEntity = new CommercialActionEntity($projectEntity, $this->text($arguments, 'title', 255), $this->dueOn($arguments));
        $actionEntity->setDueTime($this->dueTime($arguments));
        $projectEntity->addAction($actionEntity);
        $projectEntity->recordEvent('action_created', new DateTimeImmutable(), $actionEntity->getTitle())->linkAction($actionEntity);
        $this->entityManager->flush();

        return $this->taskResult($actionEntity);
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function updateTask(int $projectId, array $arguments): array
    {
        $actionEntity = $this->task($projectId, $this->positiveId($arguments, 'taskId'));
        $this->assertTaskRevision($actionEntity, $this->text($arguments, 'revision', 64));
        if (array_key_exists('dueTime', $arguments)) {
            $actionEntity->setDueTime($this->dueTime($arguments));
        }
        $this->actionWorkflow->revise($actionEntity, $this->text($arguments, 'title', 255), $this->dueOn($arguments), $actionEntity->getDetails(), $actionEntity->getPerson());
        $this->entityManager->flush();

        return $this->taskResult($actionEntity);
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function completeTask(int $projectId, array $arguments): array
    {
        $actionEntity = $this->task($projectId, $this->positiveId($arguments, 'taskId'));
        $this->assertTaskRevision($actionEntity, $this->text($arguments, 'revision', 64));
        $note = $this->optionalText($arguments, 'note', 10000);
        $this->actionWorkflow->complete($actionEntity, $note);
        $this->entityManager->flush();

        return $this->taskResult($actionEntity);
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function createAppointment(CommercialProjectEntity $projectEntity, array $arguments): array
    {
        $appointmentEntity = $this->appointmentManager->create(
            $projectEntity,
            $this->draft($arguments),
            $this->text($arguments, 'requestId', 32),
        );

        return $this->appointmentResult($appointmentEntity);
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function updateAppointment(int $projectId, array $arguments): array
    {
        $appointmentEntity = $this->appointment($projectId, $this->positiveId($arguments, 'appointmentId'));
        $this->appointmentManager->update($appointmentEntity, $this->draft($arguments), $this->positiveId($arguments, 'revision'));

        return $this->appointmentResult($appointmentEntity);
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function cancelAppointment(int $projectId, array $arguments): array
    {
        $appointmentEntity = $this->appointment($projectId, $this->positiveId($arguments, 'appointmentId'));
        $this->appointmentManager->cancel($appointmentEntity, $this->positiveId($arguments, 'revision'));

        return $this->appointmentResult($appointmentEntity);
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function setAppointmentSummary(int $projectId, array $arguments): array
    {
        $appointmentEntity = $this->appointment($projectId, $this->positiveId($arguments, 'appointmentId'));
        $this->appointmentManager->setSummary($appointmentEntity, $this->optionalText($arguments, 'summary', 10000), $this->positiveId($arguments, 'revision'));

        return $this->appointmentResult($appointmentEntity);
    }

    private function project(int $projectId): CommercialProjectEntity
    {
        $projectEntity = $this->entityManager->find(CommercialProjectEntity::class, $projectId);
        if (!$projectEntity instanceof CommercialProjectEntity) {
            throw new DomainException('Dossier not found.');
        }

        return $projectEntity;
    }

    private function task(int $projectId, int $taskId): CommercialActionEntity
    {
        $actionEntity = $this->entityManager->find(CommercialActionEntity::class, $taskId);
        if (!$actionEntity instanceof CommercialActionEntity || $actionEntity->getProject()->getId() !== $projectId) {
            throw new DomainException('Task not found in this dossier.');
        }

        return $actionEntity;
    }

    private function appointment(int $projectId, int $appointmentId): CommercialAppointmentEntity
    {
        $appointmentEntity = $this->entityManager->find(CommercialAppointmentEntity::class, $appointmentId);
        if (!$appointmentEntity instanceof CommercialAppointmentEntity || $appointmentEntity->getProject()->getId() !== $projectId) {
            throw new DomainException('Appointment not found in this dossier.');
        }

        return $appointmentEntity;
    }

    /** @param array<string, mixed> $arguments */
    private function positiveId(array $arguments, string $name): int
    {
        $value = $arguments[$name] ?? null;
        if (!is_int($value) || 1 > $value) {
            throw new DomainException("Invalid {$name}.");
        }

        return $value;
    }

    /** @param array<string, mixed> $arguments */
    private function text(array $arguments, string $name, int $length): string
    {
        $value = $arguments[$name] ?? null;
        if (!is_string($value) || '' === trim($value) || mb_strlen($value) > $length) {
            throw new DomainException("Invalid {$name}.");
        }

        return trim($value);
    }

    /** @param array<string, mixed> $arguments */
    private function optionalText(array $arguments, string $name, int $length): ?string
    {
        $value = $arguments[$name] ?? null;
        if (null === $value || '' === $value) {
            return null;
        }
        if (!is_string($value) || mb_strlen($value) > $length) {
            throw new DomainException("Invalid {$name}.");
        }

        return trim($value);
    }

    /** @param array<string, mixed> $arguments */
    private function dueOn(array $arguments): DateTimeImmutable
    {
        $raw = $this->text($arguments, 'dueOn', 10);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, new DateTimeZone('Europe/Paris'));
        if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $raw) {
            throw new DomainException('Invalid dueOn.');
        }

        return $date;
    }

    /** @param array<string, mixed> $arguments */
    private function dueTime(array $arguments): ?string
    {
        $value = $arguments['dueTime'] ?? null;
        if (null !== $value && !is_string($value)) {
            throw new DomainException('Invalid dueTime.');
        }

        return '' === $value ? null : $value;
    }

    /** @param array<string, mixed> $arguments */
    private function draft(array $arguments): CalendarEventDraft
    {
        $timeZone = new DateTimeZone('Europe/Paris');
        $startRaw = $this->text($arguments, 'startsAt', 25);
        $endRaw = $this->text($arguments, 'endsAt', 25);
        $startsAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $startRaw, $timeZone);
        $endsAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $endRaw, $timeZone);
        if (!$startsAt instanceof DateTimeImmutable || $startsAt->format('Y-m-d\TH:i') !== $startRaw || !$endsAt instanceof DateTimeImmutable || $endsAt->format('Y-m-d\TH:i') !== $endRaw) {
            throw new DomainException('Invalid appointment time.');
        }

        return new CalendarEventDraft($this->text($arguments, 'title', 255), $startsAt, $endsAt, 'Europe/Paris', $this->optionalText($arguments, 'location', 1000));
    }

    private function taskRevision(CommercialActionEntity $actionEntity): string
    {
        return hash('sha256', implode('|', [$actionEntity->getTitle(), $actionEntity->getDueOn()->format('Y-m-d'), $actionEntity->getDueTime() ?? '', $actionEntity->getStatus(), $actionEntity->getDetails() ?? '']));
    }

    private function assertTaskRevision(CommercialActionEntity $actionEntity, string $revision): void
    {
        if (!hash_equals($this->taskRevision($actionEntity), $revision)) {
            throw new DomainException('Task revision is outdated.');
        }
    }

    /** @return array<string, mixed> */
    private function taskResult(CommercialActionEntity $actionEntity): array
    {
        $projectId = $actionEntity->getProject()->getId();

        return [
            'projectId' => $projectId,
            'taskId' => $actionEntity->getId(),
            'title' => $actionEntity->getTitle(),
            'dueOn' => $actionEntity->getDueOn()->format('Y-m-d'),
            'dueTime' => $actionEntity->getDueTime(),
            'status' => $actionEntity->getStatus(),
            'revision' => $this->taskRevision($actionEntity),
            'url' => $this->urlGenerator->generate('commercial_project_show', ['id' => $projectId], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }

    /** @return array<string, mixed> */
    private function appointmentResult(CommercialAppointmentEntity $appointmentEntity): array
    {
        $projectId = $appointmentEntity->getProject()->getId();

        return [
            'projectId' => $projectId,
            'appointmentId' => $appointmentEntity->getId(),
            'title' => $appointmentEntity->getTitle(),
            'startsAt' => $appointmentEntity->getStartsAt()->format(DATE_ATOM),
            'endsAt' => $appointmentEntity->getEndsAt()->format(DATE_ATOM),
            'location' => $appointmentEntity->getLocation(),
            'status' => $appointmentEntity->getStatus(),
            'summary' => $appointmentEntity->getSummary(),
            'revision' => $appointmentEntity->getRevision(),
            'syncWarning' => null !== $appointmentEntity->getSyncError(),
            'url' => $this->urlGenerator->generate('commercial_project_show', ['id' => $projectId], UrlGeneratorInterface::ABSOLUTE_URL) . '#appointment-' . $appointmentEntity->getId(),
        ];
    }
}
