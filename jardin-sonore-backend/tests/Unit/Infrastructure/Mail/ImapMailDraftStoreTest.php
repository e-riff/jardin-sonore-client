<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Mail;

use App\Application\Mcp\ImapClientProvider;
use App\Infrastructure\Mail\ImapMailDraftStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\Query\WhereQuery;
use Webklex\PHPIMAP\Support\MessageCollection;

final class ImapMailDraftStoreTest extends TestCase
{
    public function testMessageIdReadReturnsCanonicalReferenceThatCanBeReadAgain(): void
    {
        $message = Message::fromString("Message-ID: <test@example.test>\r\nFrom: contact@example.test\r\nTo: marie@example.test\r\nSubject: Test\r\n\r\nBonjour");
        $message->uid = 56;
        $store = $this->queryStore(new DraftQueryFixture(new MessageCollection([$message])));
        $result = $store->getByMessageId('INBOX.Drafts', '<test@example.test>')->result();
        self::assertSame('draft:27:56', $result['draftMessageId']);
        self::assertSame('<test@example.test>', $result['messageId']);
        self::assertSame('Bonjour', $store->getByMessageId('INBOX.Drafts', $result['draftMessageId'])->body);
    }

    public function testSearchPreservesDecodedSubjectAndReturnsParisDateWithOffset(): void
    {
        $subject = 'TEST connecteur (é è à ç œ)';
        $message = Message::fromString("Message-ID: <test@example.test>\r\nFrom: contact@example.test\r\nTo: marie@example.test\r\nSubject: {$subject}\r\nDate: Thu, 12 Nov 2026 11:25:30 +0000\r\n\r\nBonjour");
        $message->uid = 56;
        $results = $this->queryStore(new DraftQueryFixture(new MessageCollection([$message])))->search('INBOX.Drafts', null, 1);
        self::assertSame($subject, $results[0]['subject']);
        self::assertSame('2026-11-12T12:25:30+01:00', $results[0]['date']);
    }

    public function testSummerDateUsesParisDaylightSavingOffset(): void
    {
        $message = Message::fromString("From: contact@example.test\r\nTo: marie@example.test\r\nSubject: Test\r\nDate: Sun, 12 Jul 2026 11:25:30 +0000\r\n\r\nBonjour");
        $message->uid = 56;
        $results = $this->queryStore(new DraftQueryFixture(new MessageCollection([$message])))->search('INBOX.Drafts', null, 1);
        self::assertSame('2026-07-12T13:25:30+02:00', $results[0]['date']);
    }

    public function testSearchReturnsUidValidityReferenceForManualDraftWithoutMessageId(): void
    {
        $message = Message::fromString("From: contact@example.test\r\nTo: marie@example.test\r\nSubject: Manuel\r\n\r\nBonjour");
        $message->uid = 42;
        $query = new DraftQueryFixture(new MessageCollection([$message]));
        $store = $this->queryStore($query);
        $results = $store->search('INBOX.Drafts', 'hello" OR ALL', 10);
        self::assertSame('draft:27:42', $results[0]['draftMessageId']);
        self::assertStringContainsString('UNDELETED', $query->generate_query());
        self::assertStringContainsString('TEXT "hello\\" OR ALL"', $query->generate_query());
    }

    public function testReadsManualDraftByUidAndRejectsStaleUidValidity(): void
    {
        $message = Message::fromString("From: contact@example.test\r\nTo: marie@example.test\r\nSubject: Manuel\r\n\r\nBonjour");
        $message->uid = 42;
        $query = new DraftQueryFixture(new MessageCollection([$message]));
        $store = $this->queryStore($query);
        self::assertNull($store->getByMessageId('INBOX.Drafts', 'draft:26:42'));
        self::assertSame('Bonjour', $store->getByMessageId('INBOX.Drafts', 'draft:27:42')->body);
        self::assertStringContainsString('UID 42', $query->generate_query());
    }

    public function testDuplicateMessageIdsRequireAnExplicitDraftSearch(): void
    {
        $message = Message::fromString("Message-ID: <same@example.test>\r\nFrom: contact@example.test\r\nTo: marie@example.test\r\n\r\nBonjour");
        $query = new DraftQueryFixture(new MessageCollection([$message, $message]));
        $this->expectException(RuntimeException::class);
        $this->queryStore($query)->getByMessageId('INBOX.Drafts', '<same@example.test>');
    }

    private function queryStore(DraftQueryFixture $query): ImapMailDraftStore
    {
        $folder = $this->createStub(Folder::class);
        $folder->method('status')->willReturn(['uidvalidity' => 27]);
        $folder->method('query')->willReturn($query);
        $provider = $this->createStub(ImapClientProvider::class);
        $provider->method('client')->willReturn(new DraftStoreClientFixture($folder));

        return new ImapMailDraftStore($provider);
    }

    public function testAppendsOnlyToAnExistingFolderWithDraftFlag(): void
    {
        $folder = $this->createMock(Folder::class);
        $folder->expects(self::once())->method('appendMessage')->with('MIME', ['\\Draft'])->willReturn([]);
        $client = new DraftStoreClientFixture($folder);
        $provider = $this->createStub(ImapClientProvider::class);
        $provider->method('client')->willReturn($client);

        self::assertNull((new ImapMailDraftStore($provider))->append('INBOX.Drafts', 'MIME'));
        self::assertSame('path', $client->lookupMode);
    }

    public function testMissingFolderFailsWithoutCreatingIt(): void
    {
        $client = new DraftStoreClientFixture(null);
        $provider = $this->createStub(ImapClientProvider::class);
        $provider->method('client')->willReturn($client);

        $this->expectException(RuntimeException::class);
        (new ImapMailDraftStore($provider))->append('Drafts', 'MIME');
    }
}

final class DraftQueryFixture extends WhereQuery
{
    public function __construct(private MessageCollection $messages)
    {
        parent::__construct((new ClientManager())->account('default'));
    }

    public function get(): MessageCollection
    {
        return $this->messages;
    }
}

final class DraftStoreClientFixture extends Client
{
    public ?string $lookupMode = null;

    public function __construct(private ?Folder $folder)
    {
    }

    public function getFolder(string $folder_name, ?string $delimiter = null, bool $utf7 = false): ?Folder
    {
        $this->lookupMode = 'name';

        return $this->folder;
    }

    public function getFolderByPath($folder_path, bool $utf7 = false, bool $soft_fail = false): ?Folder
    {
        $this->lookupMode = 'path';

        return $this->folder;
    }

    public function disconnect(): Client
    {
        return $this;
    }
}
