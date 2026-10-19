<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Commercial;

use App\Infrastructure\Doctrine\Commercial\DoctrineProjectSearch;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactLinkEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class DoctrineProjectSearchTest extends KernelTestCase
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

    public function testSearchMatchesPrimaryContactEmailOrganizationAndTermsWithinLimit(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(5));
        $organizationEntity = (new OrganizationEntity())->setName("Association {$suffix}");
        $personEntity = (new PersonEntity())->setFirstName('Claire')->setLastName('Martin')->setOrganization($organizationEntity);
        $personEntity->getContactDetails()?->addEmailContactLink((new EmailContactLinkEntity())->setEmailAddress("claire-{$suffix}@example.test"));
        $projectEntity = new CommercialProjectEntity("Ateliers nature {$suffix}", $organizationEntity, null, new DateTimeImmutable('2026-10-17'));
        $projectEntity->setPrimaryContact($personEntity);
        $otherProjectEntity = new CommercialProjectEntity("Ateliers musique {$suffix}", $organizationEntity, null, new DateTimeImmutable('2026-10-18'));
        $otherProjectEntity->setPrimaryContact($personEntity);
        foreach ([$organizationEntity, $personEntity, $projectEntity, $otherProjectEntity] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();

        $projectSearch = self::getContainer()->get(DoctrineProjectSearch::class);
        $byEmail = $projectSearch->search("CLAIRE-{$suffix}@example.test", null, null, 5);
        $byOrganization = $projectSearch->search(null, $suffix, null, 1);
        $byTerms = $projectSearch->search(null, null, "nature {$suffix}", 5);
        $noResults = $projectSearch->search("missing-{$suffix}@example.test", null, null, 5);

        self::assertCount(2, $byEmail);
        self::assertContains($projectEntity->getId(), array_column($byEmail, 'id'));
        self::assertSame('Claire Martin', $byEmail[1]['contact']);
        self::assertSame(CommercialProjectEntity::STATUS_DISCUSSION, $byEmail[1]['status']);
        self::assertStringEndsWith('/commercial/projects/' . $projectEntity->getId(), (string) $byEmail[1]['url']);
        self::assertCount(1, $byOrganization);
        self::assertCount(1, $byTerms);
        self::assertSame($otherProjectEntity->getId(), $byOrganization[0]['id']);
        self::assertSame([], $noResults);
    }
}
