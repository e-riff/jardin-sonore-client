<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Commercial;

use App\Infrastructure\Doctrine\Entity\CommercialInterventionEntity;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

final class CommercialInterventionMappingTest extends KernelTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testOneGoogleOccurrenceCanHaveOnlyOneLocalRecord(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $metadata = $entityManager->getClassMetadata(CommercialInterventionEntity::class);
        $table = $metadata->table;

        self::assertSame('commercial_intervention', $table['name']);
        self::assertSame(['calendar_id', 'google_event_id'], $table['uniqueConstraints']['uniq_commercial_intervention_google_event']['columns']);
        self::assertTrue($metadata->associationMappings['project']->joinColumns[0]->nullable);
    }
}
