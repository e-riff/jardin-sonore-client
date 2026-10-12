<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Commercial;

use App\Kernel;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;

final class CommercialDateDisplayTest extends KernelTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testUtcTimestampsAreDisplayedInParisTimeInSummerAndWinter(): void
    {
        self::bootKernel();
        $twig = self::getContainer()->get(Environment::class);
        self::assertInstanceOf(Environment::class, $twig);
        $template = $twig->createTemplate('{{ value|date("d/m/Y H:i") }}');

        self::assertSame('12/10/2026 17:31', $template->render([
            'value' => new DateTimeImmutable('2026-10-12 15:31:00', new DateTimeZone('UTC')),
        ]));
        self::assertSame('12/01/2026 16:31', $template->render([
            'value' => new DateTimeImmutable('2026-01-12 15:31:00', new DateTimeZone('UTC')),
        ]));
    }
}
