<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mcp;

use App\Application\Mcp\PersonalNotificationSender;
use App\Application\Mcp\PersonalNotificationStore;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

final class PersonalNotificationSenderTest extends TestCase
{
    public function testSendsSafeHtmlOnlyToConfiguredRecipientAndDoesNotResend(): void
    {
        $store = new NotificationStoreFixture();
        $transport = new NotificationTransportFixture();
        $sender = $this->sender($store, $transport);
        $arguments = ['requestId' => 'veille:2026-11-12', 'subject' => 'Veille', 'body' => 'Bonjour', 'htmlBody' => '<p>Bonjour</p><script>alert(1)</script>'];
        self::assertSame('accepted', $sender->send($arguments)['status']);
        self::assertSame('accepted', $sender->send($arguments)['status']);
        self::assertCount(1, $transport->messages);
        $message = $transport->messages[0];
        self::assertInstanceOf(Email::class, $message);
        self::assertSame('personal@example.test', $message->getTo()[0]->getAddress());
        self::assertSame('contact@example.test', $message->getFrom()[0]->getAddress());
        self::assertSame('<p>Bonjour</p>', $message->getHtmlBody());
        self::assertSame([], $message->getCc());
        self::assertSame([], $message->getBcc());
    }

    public function testSameKeyWithChangedContentConflicts(): void
    {
        $store = new NotificationStoreFixture();
        $transport = new NotificationTransportFixture();
        $sender = $this->sender($store, $transport);
        $arguments = ['requestId' => 'daily:1', 'subject' => 'Veille', 'body' => 'Bonjour'];
        $sender->send($arguments);
        self::assertSame('conflict', $sender->send(array_replace($arguments, ['body' => 'Autre contenu']))['status']);
        self::assertCount(1, $transport->messages);
    }

    public function testUncertainTransportOutcomeIsNotRetried(): void
    {
        $store = new NotificationStoreFixture();
        $transport = new NotificationTransportFixture(true);
        $sender = $this->sender($store, $transport);
        $arguments = ['requestId' => 'daily:2', 'subject' => 'Veille', 'body' => 'Bonjour'];
        self::assertSame('uncertain', $sender->send($arguments)['status']);
        self::assertSame('uncertain', $sender->send($arguments)['status']);
        self::assertCount(1, $transport->messages);
    }

    public function testRejectsRecipientArgumentBeforeReservingOrSending(): void
    {
        $store = new NotificationStoreFixture();
        $transport = new NotificationTransportFixture();
        $sender = $this->sender($store, $transport);
        $this->expectException(InvalidArgumentException::class);
        try {
            $sender->send(['requestId' => 'daily:3', 'subject' => 'Veille', 'body' => 'Bonjour', 'to' => 'other@example.test']);
        } finally {
            self::assertSame([], $store->rows);
            self::assertSame([], $transport->messages);
        }
    }

    private function sender(NotificationStoreFixture $store, NotificationTransportFixture $transport): PersonalNotificationSender
    {
        return new PersonalNotificationSender($store, $transport, new NullLogger(), 'contact@example.test', 'personal@example.test');
    }
}

final class NotificationStoreFixture implements PersonalNotificationStore
{
    /** @var array<string, array{fingerprint: string, status: string}> */
    public array $rows = [];

    public function reserve(string $requestId, string $fingerprint): array
    {
        if (isset($this->rows[$requestId])) {
            return ['status' => $this->rows[$requestId]['fingerprint'] === $fingerprint ? $this->rows[$requestId]['status'] : 'conflict', 'isNew' => false];
        }
        $this->rows[$requestId] = ['fingerprint' => $fingerprint, 'status' => 'sending'];

        return ['status' => 'sending', 'isNew' => true];
    }

    public function finish(string $requestId, string $status): void
    {
        $this->rows[$requestId]['status'] = $status;
    }
}

final class NotificationTransportFixture implements TransportInterface
{
    /** @var list<RawMessage> */
    public array $messages = [];

    public function __construct(private bool $fails = false)
    {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $this->messages[] = $message;
        if ($this->fails) {
            throw new RuntimeException('SMTP connection lost');
        }

        return new SentMessage($message, $envelope ?? Envelope::create($message));
    }

    public function __toString(): string
    {
        return 'test://';
    }
}
