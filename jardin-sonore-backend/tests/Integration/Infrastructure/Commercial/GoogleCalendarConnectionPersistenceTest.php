<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Commercial;

use App\Infrastructure\Doctrine\Entity\GoogleCalendarConnectionEntity;
use App\Infrastructure\Google\CalendarRefreshTokenCipher;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class GoogleCalendarConnectionPersistenceTest extends KernelTestCase
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

    public function testRefreshTokenIsStoredEncryptedAndSurvivesReload(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $cipher = new CalendarRefreshTokenCipher(base64_encode(str_repeat('a', 32)));
        $connectionEntity = new GoogleCalendarConnectionEntity(
            $cipher->encrypt('refresh-token'),
            'owner@example.test',
            new DateTimeImmutable('2026-10-16 10:00:00'),
        );
        $entityManager->persist($connectionEntity);
        $entityManager->flush();
        $entityManager->clear();

        $reloadedEntity = $entityManager->find(GoogleCalendarConnectionEntity::class, GoogleCalendarConnectionEntity::SINGLETON_ID);

        self::assertInstanceOf(GoogleCalendarConnectionEntity::class, $reloadedEntity);
        self::assertNotSame('refresh-token', $reloadedEntity->getEncryptedRefreshToken());
        self::assertSame('refresh-token', $cipher->decrypt($reloadedEntity->getEncryptedRefreshToken()));
        self::assertSame('owner@example.test', $reloadedEntity->getAccountEmail());
    }
}
