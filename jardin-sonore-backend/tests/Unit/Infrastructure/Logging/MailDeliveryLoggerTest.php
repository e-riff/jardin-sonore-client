<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Logging;

use App\Infrastructure\Logging\MailDeliveryLogger;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mailer\Event\FailedMessageEvent;
use Symfony\Component\Mailer\Event\SentMessageEvent;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;

final class MailDeliveryLoggerTest extends TestCase
{
    public function testTransportLogsDepartureOnlyAfterSuccessfulSend(): void
    {
        $this->checkDelivery(false);
    }

    public function testTransportFailureDoesNotLogDepartureOrSensitiveContent(): void
    {
        $this->checkDelivery(true);
    }

    private function checkDelivery(bool $fail): void
    {
        $testHandler = new TestHandler();
        $mailDeliveryLogger = new MailDeliveryLogger(new Logger('mail', [$testHandler]));
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(SentMessageEvent::class, $mailDeliveryLogger->onSent(...));
        $eventDispatcher->addListener(FailedMessageEvent::class, $mailDeliveryLogger->onFailed(...));
        $transport = new class($eventDispatcher, $fail) extends AbstractTransport {
            public function __construct(EventDispatcher $eventDispatcher, private bool $fail)
            {
                parent::__construct($eventDispatcher);
            }

            protected function doSend(SentMessage $message): void
            {
                if ($this->fail) {
                    throw new TransportException('Secret SMTP diagnostic');
                }
            }

            public function __toString(): string
            {
                return 'test://';
            }
        };
        $email = (new Email())->from('sender@example.test')->to('recipient@example.test')->subject('Invitation')->text('secret-token');
        self::assertCount(0, $testHandler->getRecords());
        try {
            $transport->send($email);
            self::assertFalse($fail);
        } catch (TransportException) {
            self::assertTrue($fail);
        }
        $records = $testHandler->getRecords();
        self::assertCount(1, $records);
        self::assertSame($fail ? 'Mail transport failed.' : 'Mail transport accepted.', $records[0]->message);
        self::assertSame(['recipient@example.test'], $records[0]->context['recipients']);
        self::assertSame('Invitation', $records[0]->context['subject']);
        self::assertSame($fail ? 'ERROR' : 'INFO', $records[0]->level->getName());
        self::assertStringNotContainsString('secret-token', serialize($records));
        self::assertStringNotContainsString('Secret SMTP diagnostic', serialize($records));
        if (!$fail) {
            self::assertNotEmpty($records[0]->context['message_id']);
        }
    }
}
