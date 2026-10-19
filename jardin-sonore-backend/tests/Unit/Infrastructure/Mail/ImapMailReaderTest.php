<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Mail;

use App\Application\Mcp\ImapClientProvider;
use App\Application\Mcp\MailReadException;
use App\Infrastructure\Mail\ImapMailReader;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Query\WhereQuery;
use Webklex\PHPIMAP\Support\MessageCollection;

final class ImapMailReaderTest extends TestCase
{
    public function testSearchUsesFiltersAndLeavesMessagesUnread(): void
    {
        $olderMessage = new ImapMessageFixture(14, '<mail-14@example.test>', 'Projet éveil', 'Bonjour', '', 0);
        $newerMessage = new ImapMessageFixture(15, '<mail-15@example.test>', 'Nouvelle réponse', 'Bonjour', '', 0);
        $query = $this->createMock(WhereQuery::class);
        $query->expects(self::once())->method('whereFrom')->with('marie@example.test')->willReturnSelf();
        $query->expects(self::once())->method('whereSince')->with(self::isInstanceOf(DateTimeImmutable::class))->willReturnSelf();
        $query->expects(self::once())->method('whereText')->with('éveil')->willReturnSelf();
        $query->expects(self::once())->method('setFetchBody')->with(false)->willReturnSelf();
        $query->expects(self::once())->method('setFetchFlags')->with(false)->willReturnSelf();
        $query->expects(self::once())->method('setFetchOrder')->with('desc')->willReturnSelf();
        $query->expects(self::once())->method('leaveUnread')->willReturnSelf();
        $query->expects(self::once())->method('limit')->with(8)->willReturnSelf();
        $query->method('get')->willReturn(new MessageCollection([$olderMessage, $newerMessage]));
        $folder = $this->createStub(Folder::class);
        $folder->method('status')->willReturn(['UIDVALIDITY' => 77]);
        $folder->method('query')->willReturn($query);
        $client = $this->createMock(Client::class);
        $client->method('connect')->willReturnSelf();
        $client->method('disconnect')->willReturnSelf();
        $client->method('getFolder')->with('INBOX')->willReturn($folder);
        $provider = $this->createStub(ImapClientProvider::class);
        $provider->method('client')->willReturn($client);
        $reader = new ImapMailReader($provider);

        $result = $reader->search('marie@example.test', '2026-10-01', 'éveil', 8);

        self::assertCount(2, $result);
        self::assertSame('Nouvelle réponse', $result[0]['subject']);
        self::assertSame(15, $result[0]['uid']);
        self::assertSame('Projet éveil', $result[1]['subject']);
        self::assertSame(14, $result[1]['uid']);
        self::assertSame(77, $result[0]['uidValidity']);
        self::assertArrayHasKey('mailReference', $result[0]);
    }

    public function testReadRejectsStaleUidValidityBeforeFetchingBody(): void
    {
        $folder = $this->createMock(Folder::class);
        $folder->method('status')->willReturn(['UIDVALIDITY' => 78]);
        $folder->expects(self::never())->method('query');
        $client = $this->createMock(Client::class);
        $client->method('connect')->willReturnSelf();
        $client->method('disconnect')->willReturnSelf();
        $client->method('getFolder')->with('INBOX')->willReturn($folder);
        $provider = $this->createStub(ImapClientProvider::class);
        $provider->method('client')->willReturn($client);
        $reader = new ImapMailReader($provider);

        $this->expectException(MailReadException::class);
        $reader->readThread(self::mailReference(77, 14), 3);
    }

    public function testReadTruncatesTextAndReturnsAttachmentPresenceWithoutMarkingRead(): void
    {
        $message = new ImapMessageFixture(14, '<mail-14@example.test>', 'Projet', str_repeat('a', 9_000), '', 2);
        $query = $this->createMock(WhereQuery::class);
        $query->expects(self::once())->method('whereUid')->with(14)->willReturnSelf();
        $query->expects(self::once())->method('setFetchBody')->with(true)->willReturnSelf();
        $query->expects(self::once())->method('setFetchFlags')->with(false)->willReturnSelf();
        $query->expects(self::once())->method('leaveUnread')->willReturnSelf();
        $query->expects(self::once())->method('limit')->with(1)->willReturnSelf();
        $query->method('get')->willReturn(new MessageCollection([$message]));
        $folder = $this->createStub(Folder::class);
        $folder->method('status')->willReturn(['UIDVALIDITY' => 77]);
        $folder->method('query')->willReturn($query);
        $client = $this->createMock(Client::class);
        $client->method('connect')->willReturnSelf();
        $client->method('disconnect')->willReturnSelf();
        $client->method('getFolder')->with('INBOX')->willReturn($folder);
        $provider = $this->createStub(ImapClientProvider::class);
        $provider->method('client')->willReturn($client);
        $reader = new ImapMailReader($provider);

        $result = $reader->readThread(self::mailReference(77, 14), 1);

        self::assertCount(1, $result);
        self::assertSame(8_000, mb_strlen((string) $result[0]['body']));
        self::assertTrue($result[0]['hasAttachments']);
        self::assertSame(2, $result[0]['attachmentCount']);
    }

    public function testReadSupportsMessagesThatExposeUidThroughMagicCall(): void
    {
        $message = new MagicUidImapMessageFixture(new ImapMessageFixture(14, '<mail-14@example.test>', 'Projet', 'Bonjour', '', 0));
        $query = $this->createMock(WhereQuery::class);
        $query->expects(self::once())->method('whereUid')->with(14)->willReturnSelf();
        $query->method('setFetchBody')->willReturnSelf();
        $query->method('setFetchFlags')->willReturnSelf();
        $query->method('leaveUnread')->willReturnSelf();
        $query->method('limit')->willReturnSelf();
        $query->method('get')->willReturn(new MessageCollection([$message]));
        $folder = $this->createStub(Folder::class);
        $folder->method('status')->willReturn(['UIDVALIDITY' => 77]);
        $folder->method('query')->willReturn($query);
        $client = $this->createMock(Client::class);
        $client->method('connect')->willReturnSelf();
        $client->method('disconnect')->willReturnSelf();
        $client->method('getFolder')->with('INBOX')->willReturn($folder);
        $provider = $this->createStub(ImapClientProvider::class);
        $provider->method('client')->willReturn($client);
        $reader = new ImapMailReader($provider);

        $result = $reader->readThread(self::mailReference(77, 14), 1);

        self::assertCount(1, $result);
        self::assertSame('Bonjour', $result[0]['body']);
    }

    public function testConnectionFailureDoesNotExposeUnderlyingException(): void
    {
        $provider = $this->createStub(ImapClientProvider::class);
        $provider->method('client')->willThrowException(new RuntimeException('password is secret'));
        $reader = new ImapMailReader($provider);

        try {
            $reader->search(null, '2026-10-01', null, 5);
            self::fail('Expected a safe read exception.');
        } catch (MailReadException $exception) {
            self::assertSame('Mailbox read failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString('secret', $exception->getMessage());
        }
    }

    private static function mailReference(int $uidValidity, int $uid): string
    {
        return rtrim(strtr(base64_encode(json_encode(['folder' => 'INBOX', 'uidValidity' => $uidValidity, 'uid' => $uid], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }
}

final readonly class ImapMessageFixture
{
    public function __construct(
        private int $uid,
        private string $messageId,
        private string $subject,
        private string $textBody,
        private string $htmlBody,
        private int $attachmentCount,
    ) {
    }

    public function getUid(): int
    {
        return $this->uid;
    }

    public function getMessageId(): \Webklex\PHPIMAP\Attribute
    {
        return new \Webklex\PHPIMAP\Attribute('message-id', $this->messageId);
    }

    public function getSubject(): \Webklex\PHPIMAP\Attribute
    {
        return new \Webklex\PHPIMAP\Attribute('subject', $this->subject);
    }

    public function getFrom(): \Webklex\PHPIMAP\Attribute
    {
        return new \Webklex\PHPIMAP\Attribute('from', new \Webklex\PHPIMAP\Address((object) ['mailbox' => 'marie', 'host' => 'example.test', 'personal' => 'Marie']));
    }

    public function getTo(): \Webklex\PHPIMAP\Attribute
    {
        return new \Webklex\PHPIMAP\Attribute('to', new \Webklex\PHPIMAP\Address((object) ['mailbox' => 'contact', 'host' => 'example.test']));
    }

    public function getDate(): \Webklex\PHPIMAP\Attribute
    {
        return new \Webklex\PHPIMAP\Attribute('date', new \Carbon\CarbonImmutable('2026-10-17T10:00:00+02:00'));
    }

    public function getTextBody(): string
    {
        return $this->textBody;
    }

    public function getHTMLBody(): string
    {
        return $this->htmlBody;
    }

    public function getAttachments(): Collection
    {
        return new Collection(array_fill(0, $this->attachmentCount, new stdClass()));
    }

    public function getInReplyTo(): \Webklex\PHPIMAP\Attribute
    {
        return new \Webklex\PHPIMAP\Attribute('in-reply-to', null);
    }
}

final readonly class MagicUidImapMessageFixture
{
    public function __construct(private ImapMessageFixture $message)
    {
    }

    public function __call(string $method, array $arguments): mixed
    {
        if ('getUid' === $method) {
            return 14;
        }

        return $this->message->{$method}(...$arguments);
    }
}
