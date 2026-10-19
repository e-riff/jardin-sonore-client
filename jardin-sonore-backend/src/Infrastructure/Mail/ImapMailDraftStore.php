<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Application\Mcp\ImapClientProvider;
use App\Application\Mcp\MailDraftStore;
use RuntimeException;
use Throwable;
use Webklex\PHPIMAP\Folder;

final readonly class ImapMailDraftStore implements MailDraftStore
{
    public function __construct(private ImapClientProvider $clientProvider)
    {
    }

    public function findByMessageId(string $folder, string $messageId): ?int
    {
        if (1 !== preg_match('/^<[^<>\s]{1,250}>$/D', $messageId)) {
            throw new RuntimeException('Invalid draft identifier.');
        }

        try {
            $message = $this->folder($folder)->query()->whereMessageId($messageId)->setFetchBody(false)->setFetchFlags(false)->limit(1)->leaveUnread()->get()->first();

            return is_object($message) && is_callable([$message, 'getUid']) ? $message->getUid() : null;
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new RuntimeException('Draft lookup failed.');
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

    private function folder(string $folderName): Folder
    {
        if ('' === trim($folderName)) {
            throw new RuntimeException('Draft folder is not configured.');
        }
        $folder = $this->clientProvider->client()->getFolder($folderName);
        if (!$folder instanceof Folder) {
            throw new RuntimeException('Draft folder is not available.');
        }

        return $folder;
    }
}
