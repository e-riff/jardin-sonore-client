<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Application\Mcp\ImapClientProvider;
use App\Application\Mcp\MailReader;
use App\Application\Mcp\MailReadException;
use DateTimeImmutable;
use JsonException;
use Throwable;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Folder;

final readonly class ImapMailReader implements MailReader
{
    private const int BODY_LIMIT = 8_000;

    public function __construct(private ImapClientProvider $clientProvider)
    {
    }

    /** @return list<array<string, scalar|null>> */
    public function search(?string $from, ?string $since, ?string $query, int $limit): array
    {
        if (1 > $limit || 25 < $limit) {
            throw new MailReadException('Mailbox read failed.');
        }

        try {
            $client = $this->clientProvider->client();
            $folder = $this->inbox($client->getFolder('INBOX'));
            $uidValidity = $this->uidValidity($folder);
            $messageQuery = $folder->query();
            if (null !== $from) {
                $messageQuery->whereFrom($from);
            }
            if (null !== $since) {
                $messageQuery->whereSince(new DateTimeImmutable($since));
            }
            if (null !== $query) {
                $messageQuery->whereText($this->escapeSearchTerm($query));
            }

            $messages = $messageQuery
                ->setFetchBody(false)
                ->setFetchFlags(false)
                ->setFetchOrder('desc')
                ->limit($limit)
                ->leaveUnread()
                ->get();

            $results = [];
            foreach ($messages as $message) {
                $results[] = $this->metadata($message, $uidValidity);
            }

            usort($results, static fn (array $firstMessage, array $secondMessage): int => $secondMessage['uid'] <=> $firstMessage['uid']);

            return $results;
        } catch (MailReadException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new MailReadException('Mailbox read failed.');
        }
    }

    /** @return list<array<string, scalar|null>> */
    public function readThread(string $mailReference, int $limit): array
    {
        if (1 > $limit || 10 < $limit) {
            throw new MailReadException('Mailbox read failed.');
        }

        try {
            $reference = $this->decodeReference($mailReference);
            $client = $this->clientProvider->client();
            $folder = $this->inbox($client->getFolder('INBOX'));
            if ($reference['uidValidity'] !== $this->uidValidity($folder)) {
                throw new MailReadException('The mail reference is out of date.');
            }

            $message = $folder->query()
                ->whereUid($reference['uid'])
                ->setFetchBody(true)
                ->setFetchFlags(false)
                ->limit(1)
                ->leaveUnread()
                ->get()
                ->first();
            if (!is_object($message) || !is_callable([$message, 'getUid'])) {
                throw new MailReadException('The requested message is no longer available.');
            }

            $messages = [$message];
            $messageId = $this->attributeString($message->getMessageId());
            $parentId = $this->attributeString($message->getInReplyTo());
            if (null !== $parentId && $this->validMessageId($parentId) && count($messages) < $limit) {
                $parentMessage = $folder->query()
                    ->whereMessageId($parentId)
                    ->setFetchBody(true)
                    ->setFetchFlags(false)
                    ->limit(1)
                    ->leaveUnread()
                    ->get()
                    ->first();
                if (is_object($parentMessage) && is_callable([$parentMessage, 'getUid'])) {
                    $messages[] = $parentMessage;
                }
            }

            if (null !== $messageId && $this->validMessageId($messageId) && count($messages) < $limit) {
                $replies = $folder->query()
                    ->whereInReplyTo($messageId)
                    ->setFetchBody(true)
                    ->setFetchFlags(false)
                    ->limit($limit - count($messages))
                    ->leaveUnread()
                    ->get();
                foreach ($replies as $replyMessage) {
                    if (is_object($replyMessage) && is_callable([$replyMessage, 'getUid']) && $replyMessage->getUid() !== $message->getUid()) {
                        $messages[] = $replyMessage;
                    }
                }
            }

            $results = [];
            foreach ($messages as $threadMessage) {
                $results[] = $this->messageContent($threadMessage, $reference['uidValidity']);
            }

            return array_slice($results, 0, $limit);
        } catch (MailReadException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new MailReadException('Mailbox read failed.');
        }
    }

    private function inbox(?Folder $folder): Folder
    {
        if (!$folder instanceof Folder) {
            throw new MailReadException('Mailbox read failed.');
        }

        return $folder;
    }

    private function uidValidity(Folder $folder): int
    {
        $status = $folder->status();
        foreach ($status as $key => $value) {
            if ('UIDVALIDITY' === strtoupper((string) $key) && is_numeric($value) && 0 < (int) $value) {
                return (int) $value;
            }
        }

        throw new MailReadException('Mailbox read failed.');
    }

    /** @return array<string, scalar|null> */
    private function metadata(object $message, int $uidValidity): array
    {
        $from = $message->getFrom()->first();
        $to = array_map(static fn (mixed $address): string => $address instanceof Address ? $address->toString() : (string) $address, $message->getTo()->toArray());

        return [
            'mailReference' => $this->encodeReference($uidValidity, $message->getUid()),
            'uid' => $message->getUid(),
            'uidValidity' => $uidValidity,
            'messageId' => $this->attributeString($message->getMessageId()),
            'subject' => $this->attributeString($message->getSubject()),
            'from' => $from instanceof Address ? $from->mail : (string) $from,
            'to' => implode(', ', $to),
            'date' => $message->getDate()->toDate()->format(DATE_ATOM),
            'hasAttachments' => 0 < $message->getAttachments()->count(),
        ];
    }

    /** @return array<string, scalar|null> */
    private function messageContent(object $message, int $uidValidity): array
    {
        $metadata = $this->metadata($message, $uidValidity);
        $text = trim($message->getTextBody());
        if ('' === $text) {
            $text = html_entity_decode(strip_tags($message->getHTMLBody()), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $text = mb_substr($text, 0, self::BODY_LIMIT);
        $attachmentCount = $message->getAttachments()->count();
        $metadata['body'] = $text;
        $metadata['hasAttachments'] = 0 < $attachmentCount;
        $metadata['attachmentCount'] = $attachmentCount;

        return $metadata;
    }

    private function encodeReference(int $uidValidity, int $uid): string
    {
        $payload = json_encode(['folder' => 'INBOX', 'uidValidity' => $uidValidity, 'uid' => $uid], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    /** @return array{folder: 'INBOX', uidValidity: int, uid: int} */
    private function decodeReference(string $reference): array
    {
        $decoded = base64_decode(strtr($reference, '-_', '+/'), true);
        if (false === $decoded) {
            throw new MailReadException('Invalid mail reference.');
        }

        try {
            $payload = json_decode($decoded, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new MailReadException('Invalid mail reference.');
        }

        if (!is_array($payload) || 'INBOX' !== ($payload['folder'] ?? null) || !is_int($payload['uidValidity'] ?? null) || !is_int($payload['uid'] ?? null) || 1 > $payload['uidValidity'] || 1 > $payload['uid']) {
            throw new MailReadException('Invalid mail reference.');
        }

        return ['folder' => 'INBOX', 'uidValidity' => $payload['uidValidity'], 'uid' => $payload['uid']];
    }

    private function attributeString(mixed $attribute): ?string
    {
        if (!is_object($attribute) || !method_exists($attribute, 'first')) {
            return null;
        }

        $value = $attribute->first();
        if (!is_string($value) || '' === trim($value)) {
            return null;
        }

        return trim($value);
    }

    private function validMessageId(string $messageId): bool
    {
        return 1 === preg_match('/^<[^<>\s\r\n]{1,250}>$/D', $messageId);
    }

    private function escapeSearchTerm(string $query): string
    {
        if (1 === preg_match('/[\x00-\x1F\x7F]/', $query)) {
            throw new MailReadException('Invalid search text.');
        }

        return str_replace(['\\', '"'], ['\\\\', '\\"'], $query);
    }
}
