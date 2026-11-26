<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Mailing;

use App\Domain\Model\Mailing\NewsletterRecommendationUsage;
use App\Infrastructure\Doctrine\Repository\NewsletterRecommendationUsageDoctrineRepository;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Uid\Uuid;

final class NewsletterRecommendationHistoryTest extends KernelTestCase
{
    private Connection $connection;

    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel('test', true);
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

    public function testFindsStoredUsagesByBinaryUuidAndOrdersLatestFirst(): void
    {
        $repository = self::getContainer()->get(NewsletterRecommendationUsageDoctrineRepository::class);
        $sourceUuid = Uuid::v7();
        $unusedUuid = Uuid::v7();
        $campaignUuid = Uuid::v7();
        $repository->save(new NewsletterRecommendationUsage($sourceUuid, $campaignUuid, 'Ancienne campagne', new DateTimeImmutable('2026-01-01')));
        $repository->save(new NewsletterRecommendationUsage($sourceUuid, Uuid::v7(), 'Dernière campagne', new DateTimeImmutable('2026-02-01')));
        $repository->save(new NewsletterRecommendationUsage($sourceUuid, $campaignUuid, 'Doublon à ignorer', new DateTimeImmutable('2026-03-01')));
        $usages = $repository->findBySourceRecommendationUuids([$sourceUuid, $unusedUuid]);
        self::assertArrayHasKey($sourceUuid->toRfc4122(), $usages);
        self::assertCount(2, $usages[$sourceUuid->toRfc4122()]);
        self::assertSame('Dernière campagne', $usages[$sourceUuid->toRfc4122()][0]->campaignTitle);
        self::assertArrayNotHasKey($unusedUuid->toRfc4122(), $usages);
        self::assertSame([], $repository->findBySourceRecommendationUuids([]));
    }
}
