<?php

declare(strict_types=1);

namespace App\Domain\Model\Session;

use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class SessionSequence
{
    public ?string $role;

    /** @var list<string> */
    public array $instrumentUuids;

    /** @var list<SessionSequenceMedia> */
    private array $media;

    /**
     * @param list<string>               $instrumentUuids
     * @param list<SessionSequenceMedia> $media
     */
    public function __construct(
        public Uuid $uuid,
        public SessionSequenceType $type,
        public string $title,
        public ?string $subtitle,
        public string $body,
        public ?string $lyrics,
        public ?string $gestures,
        public ?string $notes,
        public ?string $primaryUrl,
        public ?string $secondaryUrl,
        public ?string $imageUrl,
        public bool $showLyricsByDefault,
        public ?Uuid $sourceUuid = null,
        public ?SessionSequenceSourceKind $sourceKind = null,
        public ?string $sourceTitle = null,
        ?string $role = null,
        array $instrumentUuids = [],
        array $media = [],
    ) {
        $this->role = self::nullableString($role);
        $this->instrumentUuids = array_values(array_unique(array_filter(array_map(
            static fn (mixed $instrumentUuid): string => is_string($instrumentUuid) ? trim($instrumentUuid) : '',
            $instrumentUuids,
        ), static fn (string $instrumentUuid): bool => '' !== $instrumentUuid)));
        $this->media = $this->normalizeMedia($media);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid->toRfc4122(),
            'type' => $this->type->value,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'body' => $this->body,
            'lyrics' => $this->lyrics,
            'gestures' => $this->gestures,
            'notes' => $this->notes,
            'media' => array_map(
                static fn (SessionSequenceMedia $sessionSequenceMedia): array => $sessionSequenceMedia->toArray(),
                $this->getMedia(),
            ),
            'showLyricsByDefault' => $this->showLyricsByDefault,
            'role' => $this->role,
            'instrumentUuids' => $this->instrumentUuids,
            'sourceUuid' => $this->sourceUuid?->toRfc4122(),
            'sourceKind' => $this->sourceKind?->value,
            'sourceTitle' => $this->sourceTitle,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            uuid: Uuid::fromString((string) ($payload['uuid'] ?? Uuid::v4()->toRfc4122())),
            type: SessionSequenceType::from((string) $payload['type']),
            title: (string) ($payload['title'] ?? ''),
            subtitle: self::nullableString($payload['subtitle'] ?? null),
            body: (string) ($payload['body'] ?? ''),
            lyrics: self::nullableString($payload['lyrics'] ?? null),
            gestures: self::nullableString($payload['gestures'] ?? null),
            notes: self::nullableString($payload['notes'] ?? null),
            primaryUrl: self::nullableString($payload['primaryUrl'] ?? null),
            secondaryUrl: self::nullableString($payload['secondaryUrl'] ?? null),
            imageUrl: self::nullableString($payload['imageUrl'] ?? null),
            showLyricsByDefault: (bool) ($payload['showLyricsByDefault'] ?? false),
            role: self::nullableString($payload['role'] ?? null),
            instrumentUuids: is_array($payload['instrumentUuids'] ?? null) ? $payload['instrumentUuids'] : [],
            sourceUuid: isset($payload['sourceUuid']) && is_string($payload['sourceUuid']) && Uuid::isValid($payload['sourceUuid'])
                ? Uuid::fromString($payload['sourceUuid'])
                : null,
            sourceKind: isset($payload['sourceKind']) && is_string($payload['sourceKind']) && '' !== $payload['sourceKind']
                ? SessionSequenceSourceKind::from($payload['sourceKind'])
                : null,
            sourceTitle: self::nullableString($payload['sourceTitle'] ?? null),
            media: self::mediaFromPayload($payload),
        );
    }

    /** @return list<SessionSequenceMedia> */
    public function getMedia(): array
    {
        if ([] !== $this->media) {
            return $this->media;
        }

        $legacyMedia = [];
        if (null !== $this->primaryUrl) {
            $legacyMedia[] = new SessionSequenceMedia(
                label: $this->title,
                type: MediaResourceType::LINK,
                url: $this->primaryUrl,
                imageUrl: $this->imageUrl,
                featured: true,
                displayOnSession: true,
            );
        }
        if (null !== $this->secondaryUrl) {
            $legacyMedia[] = new SessionSequenceMedia(
                label: $this->secondaryUrl,
                type: MediaResourceType::LINK,
                url: $this->secondaryUrl,
                imageUrl: null,
                featured: false,
                displayOnSession: true,
            );
        }

        return $legacyMedia;
    }

    /** @param array<string, mixed> $payload
     * @return list<SessionSequenceMedia>
     */
    private static function mediaFromPayload(array $payload): array
    {
        if (!is_array($payload['media'] ?? null)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $media): SessionSequenceMedia => SessionSequenceMedia::fromArray($media),
            array_filter($payload['media'], static fn (mixed $media): bool => is_array($media)),
        ));
    }

    /** @param list<SessionSequenceMedia> $media
     * @return list<SessionSequenceMedia>
     */
    private function normalizeMedia(array $media): array
    {
        $featuredMediaCount = count(array_filter(
            $media,
            static fn (SessionSequenceMedia $sessionSequenceMedia): bool => $sessionSequenceMedia->featured,
        ));
        if (1 < $featuredMediaCount) {
            throw new InvalidArgumentException('Session sequence cannot contain multiple featured media.');
        }

        return array_values($media);
    }

    private static function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmedValue = trim($value);

        return '' === $trimmedValue ? null : $trimmedValue;
    }
}
