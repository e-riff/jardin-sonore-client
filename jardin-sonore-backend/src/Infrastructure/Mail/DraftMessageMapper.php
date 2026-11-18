<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Application\Mcp\MailDraft;
use App\Application\Mcp\MailDraftAttachment;
use ValueError;
use Webklex\PHPIMAP\Address;
use Webklex\PHPIMAP\Attachment;
use Webklex\PHPIMAP\Message;

final readonly class DraftMessageMapper
{
    public function map(Message $message, string $messageId, ?string $imapReference = null): MailDraft
    {
        $to = $this->addresses($message, 'to');
        $attachments = [];
        $contentIds = [];
        $filenames = [];
        $sourceAttachments = [];
        foreach ($message->getAttachments() as $attachment) {
            $sourceAttachments[$attachment->part_number] = $attachment;
        }
        foreach ($message->getStructure()->parts ?? [] as $part) {
            $contentIds[$part->part_number] = trim((string) $part->getHeader()?->get('content_id'), '<>');
            // Vendor attachment names are sanitized; MIME parameters preserve the actual filename.
            $filename = $part->filename;
            if (null === $filename || '' === $filename) {
                $filename = $part->name;
            }
            if (null !== $filename && '' !== $filename) {
                $filenames[$part->part_number] = $this->attachmentFilename($filename, $part->getHeader()->raw ?? '');
            }
            // Webklex omits zero-byte files from Message::getAttachments().
            if (!isset($sourceAttachments[$part->part_number]) && ('attachment' === $part->disposition
                || ('inline' === $part->disposition && isset($filenames[$part->part_number])))) {
                $sourceAttachments[$part->part_number] = new Attachment($message, $part);
            }
        }
        foreach ($sourceAttachments as $attachment) {
            $contentId = $contentIds[$attachment->part_number] ?? '';
            $filename = $filenames[$attachment->part_number] ?? (string) $attachment->name;
            $attachments[] = new MailDraftAttachment(
                '' === $filename ? 'attachment' : $filename,
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

        return $this->decodeMimeWords($subject);
    }

    private function attachmentFilename(string $filename, string $rawPartHeaders): string
    {
        if (1 === preg_match("/^([^']+)'[^']*'(.*)$/sD", $filename, $matches)) {
            try {
                return mb_convert_encoding(rawurldecode($matches[2]), 'UTF-8', $matches[1]);
            } catch (ValueError) {
                return $filename;
            }
        }

        if (1 === preg_match('/^Content-Disposition:[ \t]*(.*(?:\r?\n[ \t]+.*)*)/mi', $rawPartHeaders, $headerMatches)) {
            $contentDisposition = preg_replace('/\r?\n[ \t]+/', ' ', $headerMatches[1]) ?? $headerMatches[1];
            if (1 === preg_match('/(?:^|;)\s*filename\s*=\s*"((?:[^\\\\]|\\\\.)*?)"(?=\s*(?:;|$))/is', $contentDisposition, $matches)) {
                $filename = preg_replace('/\\\\(.)/s', '$1', $matches[1]) ?? $filename;
            }
        }

        return $this->decodeMimeWords($filename);
    }

    private function decodeMimeWords(string $value): string
    {
        // Decode only encoded words: iconv on an already-decoded UTF-8 header drops accents.
        return preg_replace_callback(
            '/=\?[^?\s]+\?[bqBQ]\?[^?]*\?=(?:[ \t]+=\?[^?\s]+\?[bqBQ]\?[^?]*\?=)*/',
            static function (array $matches): string {
                $decoded = iconv_mime_decode($matches[0], ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

                return false === $decoded ? $matches[0] : $decoded;
            },
            $value,
        ) ?? $value;
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
