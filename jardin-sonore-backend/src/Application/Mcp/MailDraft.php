<?php

declare(strict_types=1);

namespace App\Application\Mcp;

final readonly class MailDraft
{
    /**
     * @param list<string>              $additionalRecipients
     * @param list<string>              $cc
     * @param list<string>              $bcc
     * @param list<MailDraftAttachment> $attachments
     */
    public function __construct(
        public int $uid,
        public string $messageId,
        public ?string $subject,
        public string $recipient,
        public string $body,
        public ?string $htmlBody = null,
        public array $additionalRecipients = [],
        public array $cc = [],
        public array $bcc = [],
        public array $attachments = [],
        public ?string $inReplyTo = null,
        public ?string $references = null,
        public ?string $imapReference = null,
    ) {
    }

    public function snapshotRevision(): string
    {
        $attachments = array_map(static fn (MailDraftAttachment $attachment): array => $attachment->metadata(), $this->attachments);
        usort($attachments, static fn (array $left, array $right): int => $left['id'] <=> $right['id']);

        return hash('sha256', json_encode([
            $this->subject, $this->recipient, $this->additionalRecipients, $this->cc, $this->bcc,
            str_replace(["\r\n", "\r"], "\n", $this->body),
            null === $this->htmlBody ? null : str_replace(["\r\n", "\r"], "\n", $this->htmlBody),
            $this->inReplyTo, $this->references,
            $attachments,
        ], JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    public function result(): array
    {
        return [
            'status' => 'saved', 'draftMessageId' => $this->imapReference ?? $this->messageId,
            'messageId' => str_starts_with($this->messageId, '<') ? $this->messageId : null,
            'revision' => $this->snapshotRevision(), 'to' => $this->recipient,
            'recipients' => array_values(array_filter([$this->recipient, ...$this->additionalRecipients])),
            'cc' => $this->cc, 'bcc' => $this->bcc, 'subject' => $this->subject,
            'body' => $this->body, 'htmlBody' => $this->htmlBody,
            'attachments' => array_map(static fn (MailDraftAttachment $attachment): array => $attachment->metadata(), $this->attachments),
        ];
    }

    public function revision(): string
    {
        return self::contentRevision($this->body, $this->htmlBody);
    }

    public static function contentRevision(string $body, ?string $htmlBody): string
    {
        return hash('sha256', $body . (null === $htmlBody ? '' : "\0" . $htmlBody));
    }
}
