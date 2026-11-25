<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Mcp;

use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use App\Infrastructure\Mcp\DoctrineMcpSessionWriteCoordinator;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class DoctrineMcpSessionWriteCoordinatorTest extends TestCase
{
    public function testLocksSessionBeforeExecutingMutation(): void
    {
        $entity = new SessionSummaryEntity();
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($entity);
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->willReturn($repository);
        $manager->method('wrapInTransaction')->willReturnCallback(static fn (callable $operation): mixed => $operation($manager));
        $locked = false;
        $manager->expects(self::once())->method('refresh')->with($entity, LockMode::PESSIMISTIC_WRITE)->willReturnCallback(static function () use (&$locked): void { $locked = true; });
        $coordinator = new DoctrineMcpSessionWriteCoordinator($manager, $this->createStub(MessageBusInterface::class));
        $result = $coordinator->execute([$entity->getUuid()->toRfc4122()], null, static function () use (&$locked): array {
            self::assertTrue($locked);

            return ['isError' => true];
        });
        self::assertTrue($result['isError']);
    }
}
