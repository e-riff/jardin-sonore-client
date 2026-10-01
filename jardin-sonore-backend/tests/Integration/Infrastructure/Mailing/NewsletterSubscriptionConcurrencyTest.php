<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Mailing;

use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class NewsletterSubscriptionConcurrencyTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testConcurrentRequestsCreateOneContactOneTokenAndOneEmail(): void
    {
        self::bootKernel(['environment' => 'test']);
        $connection = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        self::assertStringEndsWith('_test', (string) $connection->getDatabase());
        $emailAddress = 'concurrent-free-' . bin2hex(random_bytes(8)) . '@example.test';
        $counterPath = tempnam('/tmp', 'newsletter-counter-');
        self::assertNotFalse($counterPath);
        $barrierPath = $counterPath . '.start';
        $processes = [];
        try {
            for ($i = 0; 2 > $i; ++$i) {
                $process = proc_open([PHP_BINARY, dirname(__DIR__, 3) . '/Fixtures/newsletter-subscription-worker.php', $emailAddress, $counterPath, $barrierPath], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                self::assertIsResource($process);
                fclose($pipes[0]);
                $processes[] = [$process, $pipes];
            }
            touch($barrierPath);
            foreach ($processes as [$process, $pipes]) {
                $output = stream_get_contents($pipes[1]);
                $errors = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                self::assertSame(0, proc_close($process), (string) $output . (string) $errors);
            }
            self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM email_contact WHERE email_address = ?', [$emailAddress]));
            self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM newsletter_subscription_request r JOIN email_contact e ON r.email_contact_id = e.id WHERE e.email_address = ?', [$emailAddress]));
            self::assertSame('1', file_get_contents($counterPath));
            self::assertSame(0, (int) $connection->fetchOne('SELECT opt_in_newsletter FROM email_contact WHERE email_address = ?', [$emailAddress]));
        } finally {
            $connection->executeStatement('DELETE FROM email_contact WHERE email_address = ?', [$emailAddress]);
            // Only test-owned temporary artifacts are removed, never repository files.
            unlink($counterPath);
            if (is_file($barrierPath)) {
                unlink($barrierPath);
            }
        }
    }
}
