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

final readonly class ImapMailDraftStore implements MailDraftStore
{
    public function __construct(private ImapClientProvider $clientProvider)
    {
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
            if (!is_object($message) || !is_callable([$message, 'getUid'])) {
                return null;
            }

            $recipient = $message->getTo();
            $recipient = is_object($recipient) && is_callable([$recipient, 'toArray']) ? $recipient->toArray() : [];
            $recipientAddress = is_array($recipient) && isset($recipient[0]) && is_object($recipient[0]) && isset($recipient[0]->mail)
                ? (string) $recipient[0]->mail
                : '';

            return new MailDraft(
                $message->getUid(),
                $messageId,
                is_callable([$message, 'getSubject']) ? (string) $message->getSubject() : null,
                $recipientAddress,
                is_callable([$message, 'getTextBody']) ? $message->getTextBody() : '',
                is_callable([$message, 'getHTMLBody']) && '' !== trim($message->getHTMLBody()) ? $message->getHTMLBody() : null,
            );
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
        if (1 !== preg_match('/^<[^<>\s]{1,250}>$/D', $messageId)) {
            throw new RuntimeException('Invalid draft identifier.');
        }
        $message = $this->folder($folderName, $client)->query()->whereMessageId($messageId)
            ->setFetchBody($fetchBody)->setFetchFlags(false)->limit(1)->leaveUnread()->get()->first();

        return is_object($message) ? $message : null;
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
