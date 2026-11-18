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
    public function testAttachmentsAreSentOnceAndChangedOrRemovedFilesConflict(): void
    {
        $store = new NotificationStoreFixture();
        $transport = new NotificationTransportFixture();
        $sender = $this->sender($store, $transport);
        $file = ['filename' => 'devis.pdf', 'mimeType' => 'application/pdf', 'base64' => 'UERG'];
        $arguments = ['requestId' => 'files:1', 'subject' => 'Devis', 'body' => 'Bonjour', 'attachments' => [$file]];
        self::assertSame('accepted', $sender->send($arguments)['status']);
        self::assertSame('accepted', $sender->send($arguments)['status']);
        $message = $transport->messages[0];
        self::assertInstanceOf(Email::class, $message);
        self::assertCount(1, $message->getAttachments());
        self::assertSame('devis.pdf', $message->getAttachments()[0]->getFilename());
        self::assertSame('PDF', $message->getAttachments()[0]->getBody());
        foreach (['filename' => 'autre.pdf', 'mimeType' => 'text/plain', 'base64' => 'QVVUUkU='] as $key => $value) {
            self::assertSame('conflict', $sender->send(array_replace($arguments, ['attachments' => [array_replace($file, [$key => $value])]]))['status']);
        }
        self::assertSame('conflict', $sender->send(array_replace($arguments, ['attachments' => []]))['status']);
        self::assertCount(1, $transport->messages);
    }

    public function testEmptyAttachmentListPreservesHistoricalFingerprint(): void
    {
        $store = new NotificationStoreFixture();
        $store->reserve('historical:1', hash('sha256', json_encode(['contact@example.test', 'personal@example.test', 'Veille', 'Bonjour', null], JSON_THROW_ON_ERROR)));
        $store->finish('historical:1', 'accepted');
        $transport = new NotificationTransportFixture();
        self::assertSame('accepted', $this->sender($store, $transport)->send(['requestId' => 'historical:1', 'subject' => 'Veille', 'body' => 'Bonjour', 'attachments' => []])['status']);
        self::assertSame([], $transport->messages);
    }

    public function testInvalidAttachmentListIsRejectedBeforeReservation(): void
    {
        foreach ([null, 'file', ['filename' => 'devis.pdf'], [null], [['filename' => 'x', 'mimeType' => 'application/pdf', 'base64' => 'invalid']], array_fill(0, 11, ['filename' => 'x', 'mimeType' => 'application/pdf', 'base64' => 'UERG'])] as $attachments) {
            $store = new NotificationStoreFixture();
            $transport = new NotificationTransportFixture();
            try {
                $this->sender($store, $transport)->send(['requestId' => 'invalid:files', 'subject' => 'Veille', 'body' => 'Bonjour', 'attachments' => $attachments]);
                self::fail('Invalid attachments accepted.');
            } catch (InvalidArgumentException) {
                self::assertSame([], $store->rows);
                self::assertSame([], $transport->messages);
            }
        }
    }

    public function testReorderedAttachmentsDoNotResend(): void
    {
        $store = new NotificationStoreFixture();
        $transport = new NotificationTransportFixture();
        $sender = $this->sender($store, $transport);
        $files = [['filename' => 'a.txt', 'mimeType' => 'text/plain', 'base64' => 'YQ=='], ['filename' => 'b.txt', 'mimeType' => 'text/plain', 'base64' => 'Yg==']];
        $arguments = ['requestId' => 'order:1', 'subject' => 'Fichiers', 'body' => 'Bonjour', 'attachments' => $files];
        self::assertSame('accepted', $sender->send($arguments)['status']);
        self::assertSame('accepted', $sender->send(array_replace($arguments, ['attachments' => array_reverse($files)]))['status']);
        self::assertCount(1, $transport->messages);
    }

    public function testExplicitRecipientOverridesPersonalDefaultAndIsPartOfDeduplication(): void
    {
        $store = new NotificationStoreFixture();
        $transport = new NotificationTransportFixture();
        $sender = $this->sender($store, $transport);
        $arguments = ['requestId' => 'reminder:external:1', 'subject' => 'Rappel', 'body' => 'Bonjour', 'recipient' => 'other@example.test'];
        self::assertSame('accepted', $sender->send($arguments)['status']);
        $message = $transport->messages[0];
        self::assertInstanceOf(Email::class, $message);
        self::assertSame('other@example.test', $message->getTo()[0]->getAddress());
        self::assertSame('other@example.test', $transport->envelopes[0]->getRecipients()[0]->getAddress());
        self::assertSame('conflict', $sender->send(array_replace($arguments, ['recipient' => 'third@example.test']))['status']);
        self::assertCount(1, $transport->messages);
    }

    public function testInvalidExplicitRecipientIsRejectedBeforeReservation(): void
    {
        $store = new NotificationStoreFixture();
        $transport = new NotificationTransportFixture();
        $this->expectException(InvalidArgumentException::class);
        try {
            $this->sender($store, $transport)->send(['requestId' => 'reminder:invalid:1', 'subject' => 'Rappel', 'body' => 'Bonjour', 'recipient' => "other@example.test\r\nBcc: third@example.test"]);
        } finally {
            self::assertSame([], $store->rows);
            self::assertSame([], $transport->messages);
        }
    }

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

    public function testRejectsUnsupportedToArgumentBeforeReservingOrSending(): void
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

    /** @var list<Envelope> */
    public array $envelopes = [];

    public function __construct(private bool $fails = false)
    {
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $this->messages[] = $message;
        $this->envelopes[] = $envelope ?? Envelope::create($message);
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
