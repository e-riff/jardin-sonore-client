<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectPersonEntity;
use App\Infrastructure\Doctrine\Entity\CommercialRequestEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class CommercialPersistenceTest extends KernelTestCase
{
    private Connection $connection;

    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        self::assertStringEndsWith('_test', (string) $this->connection->getDatabase());
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        while ($this->connection->isTransactionActive()) {
            $this->connection->rollBack();
        }

        parent::tearDown();
    }

    public function testRequestProjectContactsActionsAndEventsSurviveReload(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $organizationEntity = (new OrganizationEntity())->setName('Mairie de Mornant');
        $personEntity = (new PersonEntity())->setFirstName('Claire')->setLastName('Martin')->setOrganization($organizationEntity);
        $requestEntity = new CommercialRequestEntity('site', 'Claire Martin', 'claire@example.test', 'Trois ateliers', new DateTimeImmutable('2026-10-09 08:00:00'), 'Crèche des Lilas', 'Mornant', null, bin2hex(random_bytes(16)));
        $projectEntity = new CommercialProjectEntity('Ateliers 2026-2027', $organizationEntity, $requestEntity, new DateTimeImmutable('2026-10-09 09:00:00'));
        $requestEntity->addProject($projectEntity);
        $projectEntity->setPrimaryContact($personEntity);
        $projectPersonEntity = new CommercialProjectPersonEntity($projectEntity, $personEntity, new DateTimeImmutable('2026-10-09 09:00:00'));
        $projectPersonEntity->markFormer();
        $projectEntity->addPerson($projectPersonEntity);
        $projectEntity->addAction(new CommercialActionEntity($projectEntity, 'Relancer', new DateTimeImmutable('2026-10-16'), 'Au sujet du devis', $personEntity));
        $projectEntity->recordEvent('note', new DateTimeImmutable('2026-10-09 10:00:00'), 'Appel téléphonique', ['channel' => 'phone'], $personEntity);

        $entityManager->persist($organizationEntity);
        $entityManager->persist($personEntity);
        $entityManager->persist($requestEntity);
        $entityManager->flush();
        $requestId = $requestEntity->getId();
        $entityManager->clear();

        $reloadedRequestEntity = $entityManager->find(CommercialRequestEntity::class, $requestId);
        self::assertInstanceOf(CommercialRequestEntity::class, $reloadedRequestEntity);
        self::assertSame('qualified', $reloadedRequestEntity->getStatus());
        self::assertCount(1, $reloadedRequestEntity->getProjects());
        $reloadedProjectEntity = $reloadedRequestEntity->getProjects()->first();
        self::assertInstanceOf(CommercialProjectEntity::class, $reloadedProjectEntity);
        self::assertSame('Mairie de Mornant', $reloadedProjectEntity->getOrganization()->getName());
        self::assertSame('Martin', $reloadedProjectEntity->getPrimaryContact()?->getLastName());
        self::assertFalse($reloadedProjectEntity->getPeople()->first()->isCurrent());
        self::assertSame('2026-10-16', $reloadedProjectEntity->getActions()->first()->getDueOn()->format('Y-m-d'));
        self::assertSame(['channel' => 'phone'], $reloadedProjectEntity->getEvents()->first()->getMetadata());
    }

    public function testReferencedOrganizationCannotBeHardDeleted(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $organizationEntity = (new OrganizationEntity())->setName('Mairie de Mornant');
        $requestEntity = new CommercialRequestEntity('manual', 'Claire Martin', 'claire@example.test', 'Demande', new DateTimeImmutable('2026-10-09 08:00:00'));
        $requestEntity->addProject(new CommercialProjectEntity('Ateliers', $organizationEntity, $requestEntity, new DateTimeImmutable('2026-10-09 09:00:00')));
        $entityManager->persist($organizationEntity);
        $entityManager->persist($requestEntity);
        $entityManager->flush();

        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->connection->delete('organization', ['id' => $organizationEntity->getId()]);
    }
}
