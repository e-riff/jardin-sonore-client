<?php

declare(strict_types=1);

namespace App\Domain\Model\Session;

use Symfony\Component\Uid\Uuid;

final readonly class SessionSequence
{
    public ?string $role;

    /** @var list<string> */
    public array $instrumentUuids;

    /** @param list<string> $instrumentUuids */
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
    ) {
        $this->role = self::nullableString($role);
        $this->instrumentUuids = array_values(array_unique(array_filter(array_map(
            static fn (mixed $instrumentUuid): string => is_string($instrumentUuid) ? trim($instrumentUuid) : '',
            $instrumentUuids,
        ), static fn (string $instrumentUuid): bool => '' !== $instrumentUuid)));
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
            'primaryUrl' => $this->primaryUrl,
            'secondaryUrl' => $this->secondaryUrl,
            'imageUrl' => $this->imageUrl,
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
        );
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
