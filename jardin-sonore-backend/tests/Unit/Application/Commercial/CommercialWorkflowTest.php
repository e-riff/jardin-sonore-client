<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Commercial;

use App\Application\Commercial\CommercialActionWorkflow;
use App\Application\Commercial\CommercialProjectWorkflow;
use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInvoiceEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectPersonEntity;
use App\Infrastructure\Doctrine\Entity\CommercialRequestEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class CommercialWorkflowTest extends TestCase
{
    public function testAnUnqualifiedRequestDoesNotNeedAnOrganization(): void
    {
        $requestEntity = new CommercialRequestEntity('phone', 'Mme Martin', 'martin@example.test', 'Demande à préciser', new DateTimeImmutable('2026-10-09 09:00:00'));

        self::assertNull($requestEntity->getOrganizationName());
        self::assertSame('to_qualify', $requestEntity->getStatus());
        self::assertCount(0, $requestEntity->getProjects());
    }

    public function testFormerProjectContactKeepsItsHistoricalLinkWithoutChangingDirectoryStatus(): void
    {
        [$projectEntity] = $this->discussionProject();
        $personEntity = (new PersonEntity())->setFirstName('Claire')->setLastName('Martin');
        $projectPersonEntity = new CommercialProjectPersonEntity($projectEntity, $personEntity, new DateTimeImmutable('2026-10-09 10:00:00'));
        $projectEntity->addPerson($projectPersonEntity);

        $projectPersonEntity->markFormer();

        self::assertFalse($projectPersonEntity->isCurrent());
        self::assertTrue($personEntity->isActive());
        self::assertSame([$projectPersonEntity], $projectEntity->getPeople()->toArray());
    }

    public function testOneRequestCanKeepItsOriginalMessageAcrossTwoProjects(): void
    {
        $requestEntity = new CommercialRequestEntity(
            'manual',
            'Mme Martin',
            'martin@example.test',
            'Ateliers réguliers et séance de Noël',
            new DateTimeImmutable('2026-10-09 09:00:00'),
        );
        $organizationEntity = (new OrganizationEntity())->setName('Mairie de Mornant');
        $projectWorkflow = new CommercialProjectWorkflow(new MockClock('2026-10-09 10:00:00'));

        $firstProjectEntity = $projectWorkflow->qualify($requestEntity, $organizationEntity, 'Ateliers 2026-2027');
        $secondProjectEntity = $projectWorkflow->qualify($requestEntity, $organizationEntity, 'Séance de Noël');

        self::assertSame('Ateliers réguliers et séance de Noël', $requestEntity->getMessage());
        self::assertSame('qualified', $requestEntity->getStatus());
        self::assertSame([$firstProjectEntity, $secondProjectEntity], $requestEntity->getProjects()->toArray());
        self::assertSame('discussion', $firstProjectEntity->getStatus());
        self::assertSame($organizationEntity, $secondProjectEntity->getOrganization());
    }

    public function testFinishingTheLastActionLeavesTheDiscussionProjectOpen(): void
    {
        [$projectEntity, $projectWorkflow] = $this->discussionProject();
        $actionEntity = new CommercialActionEntity($projectEntity, 'Rappeler Mme Martin', new DateTimeImmutable('2026-10-16'));
        $projectEntity->addAction($actionEntity);

        (new CommercialActionWorkflow(new MockClock('2026-10-16 10:00:00')))->complete($actionEntity, 'Accord encore en attente');

        self::assertSame('done', $actionEntity->getStatus());
        self::assertSame('discussion', $projectEntity->getStatus());
        self::assertSame('Accord encore en attente', $projectEntity->getEvents()->last()->getContent());
    }

    public function testClosingWithoutResultCancelsOpenActionsAndKeepsEarlierNotes(): void
    {
        [$projectEntity, $projectWorkflow] = $this->discussionProject();
        $projectEntity->recordEvent('note', new DateTimeImmutable('2026-10-09 11:00:00'), 'La mairie consulte les crèches.');
        $actionEntity = new CommercialActionEntity($projectEntity, 'Relancer', new DateTimeImmutable('2026-10-16'));
        $projectEntity->addAction($actionEntity);

        $projectWorkflow->closeWithoutResult($projectEntity);

        self::assertSame('without_result', $projectEntity->getStatus());
        self::assertSame('canceled', $actionEntity->getStatus());
        self::assertCount(1, $projectEntity->getEvents()->filter(
            static fn ($eventEntity): bool => 'La mairie consulte les crèches.' === $eventEntity->getContent(),
        ));
    }

    public function testConfirmedProjectCannotBeCompletedWithAnOpenAction(): void
    {
        [$projectEntity, $projectWorkflow] = $this->discussionProject();
        $projectWorkflow->confirm($projectEntity);
        $projectEntity->addAction(new CommercialActionEntity($projectEntity, 'Envoyer les documents', new DateTimeImmutable('2026-10-16')));

        $this->expectException(DomainException::class);
        $projectWorkflow->complete($projectEntity);
    }

    public function testPostponingAnActionRecordsBothDates(): void
    {
        [$projectEntity] = $this->discussionProject();
        $actionEntity = new CommercialActionEntity($projectEntity, 'Relancer', new DateTimeImmutable('2026-10-16'));
        $projectEntity->addAction($actionEntity);

        (new CommercialActionWorkflow(new MockClock('2026-10-15 08:00:00')))->postpone($actionEntity, new DateTimeImmutable('2026-10-23'));

        self::assertSame('2026-10-23', $actionEntity->getDueOn()->format('Y-m-d'));
        self::assertSame([
            'old_due_on' => '2026-10-16',
            'new_due_on' => '2026-10-23',
        ], $projectEntity->getEvents()->last()->getMetadata());
    }

    public function testCompletingAnActionCanCorrectItsTitleAndLinksTheTimelineEntry(): void
    {
        [$projectEntity] = $this->discussionProject();
        $actionEntity = new CommercialActionEntity($projectEntity, 'Relance', new DateTimeImmutable('2026-10-16'));
        $projectEntity->addAction($actionEntity);
        $workflow = new CommercialActionWorkflow(new MockClock('2026-10-16 10:00:00'));

        $workflow->complete($actionEntity, 'Appel effectué', 'Appeler la direction');

        self::assertSame('Appeler la direction', $actionEntity->getTitle());
        self::assertSame($actionEntity, $projectEntity->getEvents()->last()->getAction());
        self::assertSame('Appeler la direction', $projectEntity->getEvents()->last()->getMetadata()['action_title']);
        self::assertSame('Appel effectué', $projectEntity->getEvents()->last()->getContent());
        self::assertCount(1, $projectEntity->getEvents()->filter(static fn ($eventEntity): bool => 'action_completed' === $eventEntity->getType()));

        $this->expectException(DomainException::class);
        $workflow->complete($actionEntity, 'Deuxième validation');
    }

    public function testAFreeNoteCanBeCorrectedWithoutChangingItsDate(): void
    {
        [$projectEntity] = $this->discussionProject();
        $occurredAt = new DateTimeImmutable('2026-10-09 11:00:00');
        $eventEntity = $projectEntity->recordEvent('note', $occurredAt, 'Erreur de saisie');

        $eventEntity->revise('Compte rendu corrigé', []);

        self::assertSame($occurredAt, $eventEntity->getOccurredAt());
        self::assertSame('Compte rendu corrigé', $eventEntity->getContent());
    }

    public function testCompletedActionCanBeCorrectedThroughItsLinkedTimelineEntry(): void
    {
        [$projectEntity] = $this->discussionProject();
        $actionEntity = new CommercialActionEntity($projectEntity, 'Relance', new DateTimeImmutable('2026-10-16'));
        $projectEntity->addAction($actionEntity);
        $workflow = new CommercialActionWorkflow(new MockClock('2026-10-16 10:00:00'));
        $workflow->complete($actionEntity, 'Premier compte rendu');
        $eventEntity = $projectEntity->getEvents()->last();
        $occurredAt = $eventEntity->getOccurredAt();

        $workflow->reviseCompleted($actionEntity, 'Appel avec Camille', 'Compte rendu corrigé');

        self::assertSame('Appel avec Camille', $actionEntity->getTitle());
        self::assertSame('Appel avec Camille', $eventEntity->getMetadata()['action_title']);
        self::assertSame('Compte rendu corrigé', $eventEntity->getContent());
        self::assertSame($occurredAt, $eventEntity->getOccurredAt());
        self::assertCount(1, $projectEntity->getEvents()->filter(static fn ($candidateEventEntity): bool => 'action_completed' === $candidateEventEntity->getType()));
    }

    public function testManualStatusChangeCanReopenWithoutRestoringCanceledActions(): void
    {
        [$projectEntity, $workflow] = $this->discussionProject();
        $actionEntity = new CommercialActionEntity($projectEntity, 'Relancer', new DateTimeImmutable('2026-10-16'));
        $projectEntity->addAction($actionEntity);

        $workflow->changeStatusManually($projectEntity, CommercialProjectEntity::STATUS_WITHOUT_RESULT);
        self::assertSame(CommercialActionEntity::STATUS_CANCELED, $actionEntity->getStatus());
        $workflow->changeStatusManually($projectEntity, CommercialProjectEntity::STATUS_DISCUSSION);
        $workflow->changeStatusManually($projectEntity, CommercialProjectEntity::STATUS_CONFIRMED);

        self::assertSame(CommercialProjectEntity::STATUS_CONFIRMED, $projectEntity->getStatus());
        self::assertNull($projectEntity->getClosedAt());
        self::assertSame(CommercialActionEntity::STATUS_CANCELED, $actionEntity->getStatus());
    }

    public function testManualCompletionKeepsTheUnpaidInvoiceGuard(): void
    {
        [$projectEntity, $workflow] = $this->discussionProject();
        $workflow->confirm($projectEntity);
        $projectEntity->addInvoice(new CommercialInvoiceEntity($projectEntity, 'FAC-01', 10000, new DateTimeImmutable('2026-10-13')));

        $this->expectException(DomainException::class);
        $workflow->changeStatusManually($projectEntity, CommercialProjectEntity::STATUS_COMPLETED);
    }

    public function testManualClosureWithoutResultKeepsTheUnpaidInvoiceGuard(): void
    {
        [$projectEntity, $workflow] = $this->discussionProject();
        $workflow->confirm($projectEntity);
        $projectEntity->addInvoice(new CommercialInvoiceEntity($projectEntity, 'FAC-02', 10000, new DateTimeImmutable('2026-10-13')));

        $this->expectException(DomainException::class);
        $workflow->changeStatusManually($projectEntity, CommercialProjectEntity::STATUS_WITHOUT_RESULT);
    }

    /** @return array{0: CommercialProjectEntity, 1: CommercialProjectWorkflow} */
    private function discussionProject(): array
    {
        $requestEntity = new CommercialRequestEntity(
            'manual',
            'Mme Martin',
            'martin@example.test',
            'Demande d’ateliers',
            new DateTimeImmutable('2026-10-09 09:00:00'),
        );
        $organizationEntity = (new OrganizationEntity())->setName('Mairie de Mornant');
        $projectWorkflow = new CommercialProjectWorkflow(new MockClock('2026-10-09 10:00:00'));

        return [$projectWorkflow->qualify($requestEntity, $organizationEntity, 'Ateliers 2026-2027'), $projectWorkflow];
    }
}
