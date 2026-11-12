<?php

declare(strict_types=1);

namespace App\Application\Mcp;

use InvalidArgumentException;

final readonly class MailDraftAttachment
{
    public const int MAX_FILE_BYTES = 5 * 1024 * 1024;
    public const int MAX_TOTAL_BYTES = 10 * 1024 * 1024;
    public const int MAX_COUNT = 10;

    public function __construct(
        public string $filename,
        public string $mimeType,
        public string $content,
        public ?string $contentId = null,
        public bool $inline = false,
    ) {
    }

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        if ([] !== array_diff(array_keys($payload), ['filename', 'mimeType', 'base64'])) {
            throw new InvalidArgumentException('Unexpected attachment argument.');
        }
        $filename = $payload['filename'] ?? null;
        $mimeType = $payload['mimeType'] ?? null;
        $encoded = $payload['base64'] ?? null;
        if (!is_string($filename) || '' === trim($filename) || 255 < mb_strlen($filename)
            || 1 === preg_match('~[\x00-\x1F\x7F/\\\\]~', $filename)
            || !is_string($mimeType) || 127 < strlen($mimeType) || 1 !== preg_match('~^[a-zA-Z0-9!#$&^_.+-]+/[a-zA-Z0-9!#$&^_.+-]+$~D', $mimeType)
            || !is_string($encoded) || strlen($encoded) > 4 * (int) ceil(self::MAX_FILE_BYTES / 3)) {
            throw new InvalidArgumentException('Invalid attachment metadata or base64.');
        }
        $content = base64_decode($encoded, true);
        if (false === $content || base64_encode($content) !== $encoded || self::MAX_FILE_BYTES < strlen($content)) {
            throw new InvalidArgumentException('Attachment exceeds the file size limit.');
        }

        return new self($filename, strtolower($mimeType), $content);
    }

    /** @return array{id: string, filename: string, mimeType: string, size: int, contentId: ?string, inline: bool} */
    public function metadata(): array
    {
        return [
            'id' => hash('sha256', json_encode([$this->filename, $this->mimeType, $this->contentId, $this->inline, hash('sha256', $this->content)], JSON_THROW_ON_ERROR)),
            'filename' => $this->filename,
            'mimeType' => $this->mimeType,
            'size' => strlen($this->content),
            'contentId' => $this->contentId,
            'inline' => $this->inline,
        ];
    }

    /** @param list<self> $attachments */
    public static function assertLimits(array $attachments): void
    {
        $total = 0;
        if (self::MAX_COUNT < count($attachments)) {
            throw new InvalidArgumentException('Too many attachments.');
        }
        foreach ($attachments as $attachment) {
            $size = strlen($attachment->content);
            $total += $size;
            if (self::MAX_FILE_BYTES < $size || self::MAX_TOTAL_BYTES < $total) {
                throw new InvalidArgumentException('Attachments exceed the size limit.');
            }
        }
    }
}
