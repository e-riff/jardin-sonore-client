<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Application\Mcp\ImapClientProvider;
use App\Application\Mcp\MailDraft;
use App\Application\Mcp\MailDraftStore;
use RuntimeException;
use Throwable;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Connection\Protocols\ImapProtocol;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\IMAP;
use Webklex\PHPIMAP\Message;

final readonly class ImapMailDraftStore implements MailDraftStore
{
    public function __construct(private ImapClientProvider $clientProvider)
    {
    }

    public function search(string $folder, ?string $query, int $limit): array
    {
        if (1 > $limit || 25 < $limit || (null !== $query && (200 < mb_strlen($query) || 1 === preg_match('/[\x00-\x1F\x7F]/', $query)))) {
            throw new RuntimeException('Invalid draft search.');
        }
        $draftFolder = $this->folder($folder);
        $uidValidity = $this->uidValidity($draftFolder);
        $search = $draftFolder->query()->whereUndeleted()->setFetchBody(false)->setFetchFlags(false)->setFetchOrderDesc()->limit($limit)->leaveUnread();
        if (null !== $query && '' !== trim($query)) {
            $search->whereText(str_replace(['\\', '"'], ['\\\\', '\\"'], $query));
        }
        $results = [];
        foreach ($search->get() as $message) {
            $id = trim((string) $message->getMessageId(), '<>');
            $results[] = [
                'draftMessageId' => "draft:{$uidValidity}:{$message->getUid()}",
                'messageId' => '' === $id ? null : "<{$id}>",
                'subject' => iconv_mime_decode((string) $message->getSubject(), ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') ?: (string) $message->getSubject(),
                'to' => (string) $message->getTo(),
                'date' => (string) $message->getDate(),
            ];
        }

        return $results;
    }

    public function findByMessageId(string $folder, string $messageId): ?int
    {
        try {
            $message = $this->find($this->clientProvider->client(), $folder, $messageId, false);

            return is_object($message) && is_callable([$message, 'getUid']) ? $message->getUid() : null;
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new RuntimeException('Draft lookup failed.');
        }
    }

    public function getByMessageId(string $folder, string $messageId): ?MailDraft
    {
        try {
            $message = $this->find($this->clientProvider->client(), $folder, $messageId, true);
            if (!$message instanceof Message) {
                return null;
            }

            return (new DraftMessageMapper())->map($message, $messageId);
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new RuntimeException('Draft read failed.');
        }
    }

    public function append(string $folder, string $mime): ?int
    {
        try {
            $this->folder($folder)->appendMessage($mime, ['\\Draft']);

            return null;
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new RuntimeException('Draft save failed.');
        }
    }

    public function deleteByMessageId(string $folderName, string $messageId, int $uid): bool
    {
        if (1 > $uid) {
            throw new RuntimeException('Invalid draft UID.');
        }

        try {
            $client = $this->clientProvider->client();
            $folder = $this->folder($folderName, $client);
            $message = $this->find($client, $folderName, $messageId, false);
            if (!is_object($message) || $message->getUid() !== $uid) {
                return false;
            }

            $connection = $client->getConnection();
            if (!$connection instanceof ImapProtocol) {
                return false;
            }
            $capabilities = $connection->getCapabilities()->validatedData();
            if (!in_array('UIDPLUS', $this->flattenCapabilities($capabilities), true)) {
                return false;
            }

            $client->openFolder($folder->path);
            $connection->store(['\\Deleted'], $uid, $uid, '+', true, IMAP::ST_UID)->validatedData();
            try {
                $connection->requestAndResponse('UID EXPUNGE', [(string) $uid])->validatedData();
            } catch (Throwable $exception) {
                $connection->store(['\\Deleted'], $uid, $uid, '-', true, IMAP::ST_UID)->validatedData();
                throw $exception;
            }

            return null === $this->find($client, $folderName, $messageId, false);
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new RuntimeException('Draft deletion failed.');
        }
    }

    private function folder(string $folderName, ?Client $client = null): Folder
    {
        if ('' === trim($folderName)) {
            throw new RuntimeException('Draft folder is not configured.');
        }
        $folder = ($client ?? $this->clientProvider->client())->getFolderByPath($folderName);
        if (!$folder instanceof Folder) {
            throw new RuntimeException('Draft folder is not available.');
        }

        return $folder;
    }

    private function find(Client $client, string $folderName, string $messageId, bool $fetchBody): ?object
    {
        $folder = $this->folder($folderName, $client);
        $query = $folder->query()->whereUndeleted()->setFetchBody($fetchBody)->setFetchFlags(false)->leaveUnread();
        if (1 === preg_match('/^draft:([1-9][0-9]{0,9}):([1-9][0-9]{0,9})$/D', $messageId, $matches)) {
            if ((int) $matches[1] !== $this->uidValidity($folder)) {
                return null;
            }

            return $query->whereUid((int) $matches[2])->limit(1)->get()->first();
        }
        if (1 !== preg_match('/^<[^<>\s"\\\\]{1,250}>$/D', $messageId)) {
            throw new RuntimeException('Invalid draft identifier.');
        }
        $messages = $query->whereMessageId($messageId)->limit(2)->get();
        if (1 < $messages->count()) {
            throw new RuntimeException('Ambiguous draft identifier; search drafts again.');
        }
        $message = $messages->first();

        if (!$message instanceof Message || trim((string) $message->getMessageId(), '<>') !== trim($messageId, '<>')) {
            return null;
        }

        return $message;
    }

    private function uidValidity(Folder $folder): int
    {
        foreach ($folder->status() as $key => $value) {
            if ('UIDVALIDITY' === strtoupper((string) $key) && is_numeric($value) && 0 < (int) $value) {
                return (int) $value;
            }
        }

        throw new RuntimeException('Draft folder UIDVALIDITY is unavailable.');
    }

    /** @return list<string> */
    private function flattenCapabilities(mixed $capabilities): array
    {
        if (is_string($capabilities)) {
            return [strtoupper($capabilities)];
        }
        if (!is_array($capabilities)) {
            return [];
        }

        $flattened = [];
        foreach ($capabilities as $capability) {
            $flattened = [...$flattened, ...$this->flattenCapabilities($capability)];
        }

        return $flattened;
    }
}
