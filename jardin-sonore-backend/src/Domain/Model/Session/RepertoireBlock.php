<?php

declare(strict_types=1);

namespace App\Domain\Model\Session;

use InvalidArgumentException;

final readonly class RepertoireBlock
{
    public RepertoireBlockKind $kind;

    public ?string $text;

    public ?string $gesture;

    public function __construct(
        RepertoireBlockKind $kind,
        ?string $text = null,
        ?string $gesture = null,
    ) {
        $this->kind = $kind;
        $normalizedText = self::normalizeNullableString($text);
        $normalizedGesture = self::normalizeNullableString($gesture);

        if (RepertoireBlockKind::LINE === $kind && null === $normalizedText) {
            throw new InvalidArgumentException('Repertoire line blocks require text.');
        }

        if (RepertoireBlockKind::SECTION === $kind && null === $normalizedText) {
            throw new InvalidArgumentException('Repertoire section blocks require a title.');
        }

        $this->text = $normalizedText;
        $this->gesture = $normalizedGesture;
    }

    /**
     * @return array{kind: string, text?: string, gesture?: string}
     */
    public function toArray(): array
    {
        $payload = ['kind' => $this->kind->value];

        if (null !== self::normalizeNullableString($this->text)) {
            $payload['text'] = trim($this->text);
        }

        if (null !== self::normalizeNullableString($this->gesture)) {
            $payload['gesture'] = trim($this->gesture);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            kind: RepertoireBlockKind::from((string) ($payload['kind'] ?? RepertoireBlockKind::LINE->value)),
            text: self::nullableString($payload['text'] ?? null),
            gesture: self::nullableString($payload['gesture'] ?? null),
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

    private static function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        return self::normalizeNullableString($value);
    }
}
