<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Logging;

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mime\Email;

final class MailDeliveryLoggingTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testTransportEventWritesToDedicatedRotatingLog(): void
    {
        self::bootKernel(['environment' => 'test']);
        $eventDispatcher = self::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $eventDispatcher);
        $subject = 'Logging integration ' . bin2hex(random_bytes(8));
        $transport = new NullTransport($eventDispatcher);
        $transport->send((new Email())->from('sender@example.test')->to('recipient@example.test')->subject($subject)->text('private-body-not-for-logs'));
        $logDirectory = self::getContainer()->getParameter('kernel.logs_dir');
        self::assertIsString($logDirectory);
        $logPaths = glob("{$logDirectory}/mail-*.log");
        self::assertNotFalse($logPaths);
        $matchingLines = [];
        foreach ($logPaths as $logPath) {
            $lines = file($logPath);
            self::assertNotFalse($lines);
            foreach ($lines as $line) {
                if (str_contains($line, $subject)) {
                    $matchingLines[] = $line;
                }
            }
        }
        self::assertCount(1, $matchingLines);
        self::assertStringContainsString('mail.INFO: Mail transport accepted.', $matchingLines[0]);
        self::assertStringNotContainsString('private-body-not-for-logs', $matchingLines[0]);
    }
}
