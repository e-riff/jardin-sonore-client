<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Commercial;

use App\Application\Commercial\BuildCommercialDigest;
use App\Application\Commercial\CommercialDigest;
use App\Application\Commercial\CommercialDigestSenderInterface;
use App\Application\Commercial\DispatchCommercialDigest;
use App\Infrastructure\Doctrine\Entity\CommercialDigestDeliveryEntity;
use App\Infrastructure\Doctrine\Entity\CommercialDigestSettingsEntity;
use App\Infrastructure\Doctrine\Entity\CommercialRequestEntity;
use App\Kernel;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpKernel\KernelInterface;

final class CommercialDigestDispatchTest extends KernelTestCase
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

    public function testEligibleMondaySendsOnceAndPausePreventsNextDay(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $settingsEntity = $entityManager->find(CommercialDigestSettingsEntity::class, CommercialDigestSettingsEntity::SINGLETON_ID);
        self::assertInstanceOf(CommercialDigestSettingsEntity::class, $settingsEntity);
        $settingsEntity->setEnabled(true);
        $settingsEntity->setSendTime('08:00');
        $requestEntity = new CommercialRequestEntity('manual', 'Digest test', '', 'Suivi', new DateTimeImmutable());
        $entityManager->persist($requestEntity);
        $entityManager->flush();

        $sender = new class implements CommercialDigestSenderInterface {
            public int $count = 0;

            public function send(CommercialDigest $digest): void
            {
                ++$this->count;
            }
        };
        $clock = new MockClock('2030-10-07 09:00:00 Europe/Paris');
        $dispatcher = new DispatchCommercialDigest($entityManager, $clock, new BuildCommercialDigest($entityManager), $sender);
        self::assertTrue($dispatcher->run());
        self::assertFalse($dispatcher->run());
        self::assertSame(1, $sender->count);
        $deliveryEntity = $entityManager->getRepository(CommercialDigestDeliveryEntity::class)->findOneBy(['localDate' => new DateTimeImmutable('2030-10-07')]);
        self::assertInstanceOf(CommercialDigestDeliveryEntity::class, $deliveryEntity);
        self::assertSame(CommercialDigestDeliveryEntity::STATUS_SENT, $deliveryEntity->getStatus());

        $settingsEntity->setEnabled(false);
        $entityManager->flush();
        $clock->modify('+1 day');
        self::assertFalse($dispatcher->run());
        self::assertSame(1, $sender->count);
    }

    public function testWeekendAndTimeGateAreRespectedAndFailureCanRetry(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $settingsEntity = $entityManager->find(CommercialDigestSettingsEntity::class, CommercialDigestSettingsEntity::SINGLETON_ID);
        self::assertInstanceOf(CommercialDigestSettingsEntity::class, $settingsEntity);
        $settingsEntity->setEnabled(true);
        $settingsEntity->setSendTime('08:00');
        $entityManager->persist(new CommercialRequestEntity('manual', 'Suivi', '', 'À rappeler', new DateTimeImmutable()));
        $entityManager->flush();

        $sender = new class implements CommercialDigestSenderInterface {
            public int $count = 0;

            public function send(CommercialDigest $digest): void
            {
                ++$this->count;
                if (1 === $this->count) {
                    throw new RuntimeException('Temporary mail failure');
                }
            }
        };
        $clock = new MockClock('2030-10-05 09:00:00 Europe/Paris');
        $dispatcher = new DispatchCommercialDigest($entityManager, $clock, new BuildCommercialDigest($entityManager), $sender);
        self::assertFalse($dispatcher->run());
        $clock->modify('+2 days -1 hour -1 minute');
        self::assertFalse($dispatcher->run());
        $clock->modify('+1 minute');
        try {
            $dispatcher->run();
            self::fail('Expected the first send to fail.');
        } catch (RuntimeException $exception) {
            self::assertSame('Temporary mail failure', $exception->getMessage());
        }
        self::assertTrue($dispatcher->run());
        self::assertSame(2, $sender->count);
        $deliveryEntity = $entityManager->getRepository(CommercialDigestDeliveryEntity::class)->findOneBy(['localDate' => new DateTimeImmutable('2030-10-07')]);
        self::assertInstanceOf(CommercialDigestDeliveryEntity::class, $deliveryEntity);
        self::assertSame(2, $deliveryEntity->getAttempts());
    }
}
