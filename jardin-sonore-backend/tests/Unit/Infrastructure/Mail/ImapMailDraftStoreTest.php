<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Mail;

use App\Application\Mcp\ImapClientProvider;
use App\Infrastructure\Mail\ImapMailDraftStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Folder;

final class ImapMailDraftStoreTest extends TestCase
{
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
