<?php

declare(strict_types=1);

namespace App\Domain\Model\Session;

use InvalidArgumentException;

final readonly class SessionSequenceMedia
{
    public string $label;

    public MediaResourceType $type;

    public string $url;

    public ?string $imageUrl;

    public bool $featured;

    private bool $displayOnSession;

    public function __construct(
        string $label,
        MediaResourceType $type,
        string $url,
        ?string $imageUrl,
        bool $featured,
        bool $displayOnSession,
    ) {
        if ('' === trim($label)) {
            throw new InvalidArgumentException('Session sequence media label cannot be blank.');
        }

        if ('' === trim($url)) {
            throw new InvalidArgumentException('Session sequence media URL cannot be blank.');
        }

        $this->label = trim($label);
        $this->type = $type;
        $this->url = trim($url);
        $this->imageUrl = self::normalizeNullableString($imageUrl);
        $this->featured = $featured;
        $this->displayOnSession = $displayOnSession;
    }

    public function isDisplayedOnSession(): bool
    {
        return $this->featured || $this->displayOnSession;
    }

    /** @return array<string, scalar|null> */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'type' => $this->type->value,
            'url' => $this->url,
            'imageUrl' => $this->imageUrl,
            'featured' => $this->featured,
            'displayOnSession' => $this->displayOnSession,
        ];
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        return new self(
            label: is_string($payload['label'] ?? null) ? $payload['label'] : '',
            type: isset($payload['type']) && is_string($payload['type'])
                ? MediaResourceType::from($payload['type'])
                : MediaResourceType::LINK,
            url: is_string($payload['url'] ?? null) ? $payload['url'] : '',
            imageUrl: is_string($payload['imageUrl'] ?? null) ? $payload['imageUrl'] : null,
            featured: (bool) ($payload['featured'] ?? false),
            displayOnSession: (bool) ($payload['displayOnSession'] ?? false),
        );
    }

    private static function normalizeNullableString(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmedValue = trim($value);

        return '' === $trimmedValue ? null : $trimmedValue;
    }
}
