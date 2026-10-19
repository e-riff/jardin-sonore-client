<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Commercial;

use App\Application\Mcp\MailSource;
use App\Infrastructure\Doctrine\Commercial\DoctrineProjectFollowupWriter;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class DoctrineProjectFollowupWriterTest extends KernelTestCase
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

    public function testSummaryAndReminderAreIdempotentPerProjectAndMail(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $projectEntity = new CommercialProjectEntity('Ateliers', null, null, new DateTimeImmutable('2026-10-19'));
        $entityManager->persist($projectEntity);
        $entityManager->flush();
        $writer = new DoctrineProjectFollowupWriter($entityManager, self::getContainer()->get(UrlGeneratorInterface::class));
        $source = new MailSource('reference', str_repeat('a', 64), '<mail@example.test>', 'Sujet', 'marie@example.test', '2026-10-19T08:00:00+00:00');

        $firstSummary = $writer->recordSummary($projectEntity->getId(), $source, 'Résumé utile');
        $secondWriter = new DoctrineProjectFollowupWriter($entityManager, self::getContainer()->get(UrlGeneratorInterface::class));
        $secondSummary = $secondWriter->recordSummary($projectEntity->getId(), $source, 'Autre résumé');
        $firstReminder = $writer->createReminder($projectEntity->getId(), $source, 'Répondre', new DateTimeImmutable('2026-10-20'));
        $secondReminder = $secondWriter->createReminder($projectEntity->getId(), $source, 'Autre rappel', new DateTimeImmutable('2026-10-21'));

        self::assertFalse($firstSummary['alreadyExists']);
        self::assertTrue($secondSummary['alreadyExists']);
        self::assertSame($firstSummary['eventId'], $secondSummary['eventId']);
        self::assertFalse($firstReminder['alreadyExists']);
        self::assertTrue($secondReminder['alreadyExists']);
        self::assertSame($firstReminder['actionId'], $secondReminder['actionId']);
    }

    public function testClosedProjectDoesNotReceiveReminder(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $projectEntity = new CommercialProjectEntity('Terminé', null, null, new DateTimeImmutable('2026-10-19'));
        $projectEntity->setStatus(CommercialProjectEntity::STATUS_COMPLETED, new DateTimeImmutable('2026-10-19'));
        $entityManager->persist($projectEntity);
        $entityManager->flush();
        $writer = new DoctrineProjectFollowupWriter($entityManager, self::getContainer()->get(UrlGeneratorInterface::class));
        $source = new MailSource('reference', str_repeat('b', 64), null, 'Sujet', 'marie@example.test', '2026-10-19T08:00:00+00:00');

        $this->expectException(RuntimeException::class);
        $writer->createReminder($projectEntity->getId(), $source, 'Relancer', new DateTimeImmutable('2026-10-20'));
    }
}
