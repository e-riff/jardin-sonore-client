<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Commercial;

use App\Application\Commercial\CommercialActionWorkflow;
use App\Application\Commercial\CommercialProjectWorkflow;
use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
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

    /** @return array{0: \App\Infrastructure\Doctrine\Entity\CommercialProjectEntity, 1: CommercialProjectWorkflow} */
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
