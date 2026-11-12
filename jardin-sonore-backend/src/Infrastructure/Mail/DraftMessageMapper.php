<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Application\Mcp\MailDraft;
use App\Application\Mcp\MailDraftAttachment;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Message;

final readonly class DraftMessageMapper
{
    public function map(Message $message, string $messageId): MailDraft
    {
        $to = $this->addresses($message, 'to');
        $attachments = [];
        $contentIds = [];
        foreach ($message->getStructure()->parts ?? [] as $part) {
            $contentIds[$part->part_number] = trim((string) $part->getHeader()?->get('content_id'), '<>');
        }
        foreach ($message->getAttachments() as $attachment) {
            $contentId = $contentIds[$attachment->part_number] ?? '';
            $attachments[] = new MailDraftAttachment(
                (string) ($attachment->name ?: 'attachment'),
                (string) ($attachment->content_type ?: 'application/octet-stream'),
                (string) $attachment->content,
                '' === $contentId ? null : $contentId,
                'inline' === strtolower((string) $attachment->disposition),
            );
        }
        $html = $message->getHTMLBody();
        $inReplyTo = (string) $message->get('in_reply_to');
        $references = (string) $message->get('references');

        return new MailDraft(
            $message->getUid(), $messageId, iconv_mime_decode((string) $message->getSubject(), ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') ?: (string) $message->getSubject(), $to[0] ?? '',
            $message->getTextBody(), '' === trim($html) ? null : $html,
            array_slice($to, 1), $this->addresses($message, 'cc'), $this->addresses($message, 'bcc'),
            $attachments, $this->messageIds($inReplyTo), $this->messageIds($references),
        );
    }

    /** @return list<string> */
    private function addresses(Message $message, string $name): array
    {
        $addresses = [];
        foreach ($message->get($name)->toArray() as $address) {
            if ($address instanceof Address) {
                $addresses[] = $address->mail;
            }
        }

        return $addresses;
    }

    private function messageIds(string $value): ?string
    {
        preg_match_all('/<?([^<>,\s]+@[^<>,\s]+)>?/', $value, $matches);

        return [] === $matches[1] ? null : implode(' ', array_map(static fn (string $id): string => "<{$id}>", $matches[1]));
    }
}
