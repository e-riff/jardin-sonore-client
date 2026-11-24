<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Commercial;

use App\Infrastructure\Doctrine\Entity\SessionFamilyEntity;
use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use App\Infrastructure\Doctrine\Mapper\SessionSummaryMapper;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class SessionFamilyPersistenceTest extends KernelTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testFamilyRoundTripAndDetachmentPreserveSessionPublication(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $manager->getConnection()->beginTransaction();
        try {
            $family = (new SessionFamilyEntity())->setName('Forêt');
            $session = (new SessionSummaryEntity())->setTitle('Variante')->setPublished(true)->setFamily($family);
            $manager->persist($family);
            $manager->persist($session);
            $manager->flush();
            $mapper = self::getContainer()->get(SessionSummaryMapper::class);
            $domain = $mapper->toDomain($session);
            self::assertTrue($family->getUuid()->equals($domain->getFamilyUuid()));
            $domain->setFamilyUuid(null);
            $mapper->toEntity($domain, $session);
            self::assertNull($session->getFamily());
            self::assertTrue($session->isPublished());
        } finally {
            $manager->getConnection()->rollBack();
        }
    }
}
