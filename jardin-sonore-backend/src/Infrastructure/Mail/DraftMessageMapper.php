<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Application\Mcp\MailDraft;
use App\Application\Mcp\MailDraftAttachment;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Message;

final readonly class DraftMessageMapper
{
    public function map(Message $message, string $messageId, ?string $imapReference = null): MailDraft
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
            $message->getUid(), $messageId, $this->subject($message), $to[0] ?? '',
            $message->getTextBody(), '' === trim($html) ? null : $html,
            array_slice($to, 1), $this->addresses($message, 'cc'), $this->addresses($message, 'bcc'),
            $attachments, $this->messageIds($inReplyTo), $this->messageIds($references), $imapReference,
        );
    }

    public function subject(Message $message): string
    {
        $subject = (string) $message->getSubject();
        if (1 === preg_match('/^Subject:[ \t]*(.*(?:\r?\n[ \t]+.*)*)/mi', $message->getHeader()->raw ?? '', $matches)) {
            $subject = rtrim(preg_replace('/\r?\n[ \t]+/', ' ', $matches[1]) ?? $subject, "\r\n");
        }

        // Decode only encoded words: iconv on an already-decoded UTF-8 header drops accents.
        return preg_replace_callback(
            '/=\?[^?\s]+\?[bqBQ]\?[^?]*\?=(?:[ \t]+=\?[^?\s]+\?[bqBQ]\?[^?]*\?=)*/',
            static function (array $matches): string {
                $decoded = iconv_mime_decode($matches[0], ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

                return false === $decoded ? $matches[0] : $decoded;
            },
            $subject,
        ) ?? $subject;
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
