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

    public function testSourceUsesHeadersWithoutMarkingMailRead(): void
    {
        $message = new ImapMessageFixture(14, 'mail-14@example.test', 'Projet', 'Secret body', '', 0);
        $query = $this->createMock(WhereQuery::class);
        $query->expects(self::once())->method('whereUid')->with(14)->willReturnSelf();
        $query->expects(self::once())->method('setFetchBody')->with(false)->willReturnSelf();
        $query->expects(self::once())->method('setFetchFlags')->with(false)->willReturnSelf();
        $query->expects(self::once())->method('leaveUnread')->willReturnSelf();
        $query->method('limit')->willReturnSelf();
        $query->method('get')->willReturn(new MessageCollection([$message]));
        $folder = $this->createStub(Folder::class);
        $folder->method('status')->willReturn(['UIDVALIDITY' => 77]);
        $folder->method('query')->willReturn($query);
        $client = $this->createMock(Client::class);
        $client->method('connect')->willReturnSelf();
        $client->method('disconnect')->willReturnSelf();
        $client->method('getFolder')->willReturn($folder);
        $provider = $this->createStub(ImapClientProvider::class);
        $provider->method('client')->willReturn($client);

        $source = (new ImapMailReader($provider, 'contact@example.test'))->source(self::mailReference(77, 14));

        self::assertSame('Projet', $source->subject);
        self::assertSame('marie@example.test', $source->from);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $source->sourceKey);
        self::assertSame(hash('sha256', "contact@example.test\0mail-14@example.test"), $source->sourceKey);
    }

    public function testSourceFallsBackToUidWhenMessageIdIsMissing(): void
    {
        $message = new ImapMessageFixture(14, '', 'Projet', 'Secret body', '', 0);
        $query = $this->createMock(WhereQuery::class);
        $query->method('whereUid')->willReturnSelf();
        $query->method('setFetchBody')->willReturnSelf();
        $query->method('setFetchFlags')->willReturnSelf();
        $query->method('limit')->willReturnSelf();
        $query->method('leaveUnread')->willReturnSelf();
        $query->method('get')->willReturn(new MessageCollection([$message]));
        $folder = $this->createStub(Folder::class);
        $folder->method('status')->willReturn(['UIDVALIDITY' => 77]);
        $folder->method('query')->willReturn($query);
        $client = $this->createMock(Client::class);
        $client->method('connect')->willReturnSelf();
        $client->method('disconnect')->willReturnSelf();
        $client->method('getFolder')->willReturn($folder);
        $provider = $this->createStub(ImapClientProvider::class);
        $provider->method('client')->willReturn($client);

        $source = (new ImapMailReader($provider, 'contact@example.test'))->source(self::mailReference(77, 14));

        self::assertNull($source->messageId);
        self::assertSame(hash('sha256', "contact@example.test\0INBOX:77:14"), $source->sourceKey);
    }

    public function testReplyContextUsesTheSingleReplyToAddress(): void
    {
        $message = new ImapMessageFixture(14, '<Mail-14@Example.test>', 'Projet', '', '', 0, 'reply@example.test');
        $query = $this->createMock(WhereQuery::class);
        $query->method('whereUid')->willReturnSelf();
        $query->method('setFetchBody')->willReturnSelf();
        $query->method('setFetchFlags')->willReturnSelf();
        $query->method('limit')->willReturnSelf();
        $query->expects(self::once())->method('leaveUnread')->willReturnSelf();
        $query->method('get')->willReturn(new MessageCollection([$message]));
        $folder = $this->createStub(Folder::class);
        $folder->method('status')->willReturn(['UIDVALIDITY' => 77]);
        $folder->method('query')->willReturn($query);
        $client = $this->createMock(Client::class);
        $client->method('connect')->willReturnSelf();
        $client->method('disconnect')->willReturnSelf();
        $client->method('getFolder')->willReturn($folder);
        $provider = $this->createStub(ImapClientProvider::class);
        $provider->method('client')->willReturn($client);

        $context = (new ImapMailReader($provider, 'contact@example.test'))->replyContext(self::mailReference(77, 14));

        self::assertSame('reply@example.test', $context->replyRecipient);
        self::assertSame('<Mail-14@Example.test>', $context->messageId);
        self::assertSame('Projet', $context->subject);
    }

    public function testReplyContextRejectsMultipleReplyAddressesAndSelfAddressedMail(): void
    {
        foreach ([
            new ImapMessageFixture(14, '<source@example.test>', 'Projet', '', '', 0, ['first@example.test', 'second@example.test']),
            new ImapMessageFixture(14, '<source@example.test>', 'Projet', '', '', 0, []),
            new ImapMessageFixture(14, '<source@example.test>', 'Projet', '', '', 0, null, 'contact@example.test'),
        ] as $message) {
            $query = $this->createStub(WhereQuery::class);
            $query->method('whereUid')->willReturnSelf();
            $query->method('setFetchBody')->willReturnSelf();
            $query->method('setFetchFlags')->willReturnSelf();
            $query->method('limit')->willReturnSelf();
            $query->method('leaveUnread')->willReturnSelf();
            $query->method('get')->willReturn(new MessageCollection([$message]));
            $folder = $this->createStub(Folder::class);
            $folder->method('status')->willReturn(['UIDVALIDITY' => 77]);
            $folder->method('query')->willReturn($query);
            $client = new ReaderClientFixture($folder);
            $provider = $this->createStub(ImapClientProvider::class);
            $provider->method('client')->willReturn($client);

            try {
                (new ImapMailReader($provider, 'contact@example.test'))->replyContext(self::mailReference(77, 14));
                self::fail('Unsafe reply recipient was accepted.');
            } catch (MailReadException) {
                self::assertTrue(true);
            }
        }
    }

    public function testFindSentRepliesIsBoundedAndDoesNotMarkMessagesRead(): void
    {
        $source = new ImapMessageFixture(14, '<source@example.test>', 'Projet', '', '', 0);
        $sent = new ImapMessageFixture(20, '<sent@example.test>', 'Re: Projet', 'Réponse envoyée', '', 0);
        $inboxQuery = $this->createStub(WhereQuery::class);
        $inboxQuery->method('whereUid')->willReturnSelf();
        $inboxQuery->method('setFetchBody')->willReturnSelf();
        $inboxQuery->method('setFetchFlags')->willReturnSelf();
        $inboxQuery->method('limit')->willReturnSelf();
        $inboxQuery->method('leaveUnread')->willReturnSelf();
        $inboxQuery->method('get')->willReturn(new MessageCollection([$source]));
        $inbox = $this->createStub(Folder::class);
        $inbox->method('status')->willReturn(['UIDVALIDITY' => 77]);
        $inbox->method('query')->willReturn($inboxQuery);
        $sentQuery = $this->createMock(WhereQuery::class);
        $sentQuery->expects(self::once())->method('whereInReplyTo')->with('<source@example.test>')->willReturnSelf();
        $sentQuery->expects(self::once())->method('whereHeader')->with('References', '<source@example.test>')->willReturnSelf();
        $sentQuery->expects(self::exactly(2))->method('leaveUnread')->willReturnSelf();
        $sentQuery->method('setFetchBody')->willReturnSelf();
        $sentQuery->method('setFetchFlags')->willReturnSelf();
        $sentQuery->method('setFetchOrder')->willReturnSelf();
        $sentQuery->method('limit')->willReturnSelf();
        $sentQuery->method('get')->willReturn(new MessageCollection([$sent]));
        $sentFolder = $this->createStub(Folder::class);
        $sentFolder->method('query')->willReturn($sentQuery);
        $provider = $this->createStub(ImapClientProvider::class);
        $provider->method('client')->willReturn(new ReaderClientFixture($inbox, $sentFolder));

        $result = (new ImapMailReader($provider, 'contact@example.test', 'Sent'))->findSentReplies(self::mailReference(77, 14), 3);

        self::assertTrue($result['matchable']);
        self::assertSame('marie@example.test', $result['replyRecipient']);
        self::assertSame('Re: Projet', $result['replySubject']);
        self::assertCount(1, $result['replies']);
        self::assertSame('Réponse envoyée', $result['replies'][0]['body']);
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
        private array|string|null $replyTo = null,
        private string $fromAddress = 'marie@example.test',
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
        return new \Webklex\PHPIMAP\Attribute('from', new \Webklex\PHPIMAP\Address((object) ['mail' => $this->fromAddress, 'personal' => 'Marie']));
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

    public function getReplyTo(): \Webklex\PHPIMAP\Attribute
    {
        $addresses = array_map(static fn (string $mail): \Webklex\PHPIMAP\Address => new \Webklex\PHPIMAP\Address((object) ['mail' => $mail]), (array) $this->replyTo);

        return new \Webklex\PHPIMAP\Attribute('reply-to', $addresses);
    }

    public function getHeader(): ?\Webklex\PHPIMAP\Header
    {
        return new HeaderPresenceFixture(null !== $this->replyTo);
    }

    public function getReferences(): \Webklex\PHPIMAP\Attribute
    {
        return new \Webklex\PHPIMAP\Attribute('references', null);
    }
}

final class ReaderClientFixture extends Client
{
    public function __construct(private Folder $inbox, private ?Folder $sent = null)
    {
    }

    public function getFolder(string $folder_name, ?string $delimiter = null, bool $utf7 = false): ?Folder
    {
        return 'INBOX' === $folder_name ? $this->inbox : $this->sent;
    }

    public function disconnect(): Client
    {
        return $this;
    }
}

final class HeaderPresenceFixture extends \Webklex\PHPIMAP\Header
{
    public function __construct(private bool $replyToPresent)
    {
    }

    public function has(string $name): bool
    {
        return $this->replyToPresent && 'reply-to' === $name;
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
