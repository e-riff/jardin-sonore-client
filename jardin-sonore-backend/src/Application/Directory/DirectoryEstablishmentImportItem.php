<?php

declare(strict_types=1);

namespace App\Application\Directory;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class DirectoryEstablishmentImportItem
{
    public function __construct(
        #[Assert\NotBlank]
        public string $externalId,
        public ?string $externalOrganizationId,
        public ?string $externalDetailsUuid,
        #[Assert\NotBlank]
        public string $type,
        public ?string $name,
        #[Assert\Length(max: 2048)]
        public ?string $websiteUrl,
        public ?string $phoneNumber,
        #[Assert\Email]
        public ?string $emailAddress,
        public ?string $address,
        public ?string $commune,
        public ?int $distance,
        public ?bool $isAvip,
        /** @var array<string, mixed> */
        public array $rawPayload,
        /** @var list<string> */
        #[Assert\All([new Assert\Email()])]
        public array $additionalEmails = [],
        /** @var list<string> */
        public array $additionalPhones = [],
        /** @var list<string> */
        public array $additionalAddresses = [],
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            externalId: trim((string) ($payload['id'] ?? '')),
            externalOrganizationId: self::nullableString($payload['organizationId'] ?? null),
            externalDetailsUuid: self::nullableString($payload['detailsUuid'] ?? null),
            type: trim((string) ($payload['type'] ?? '')),
            name: self::nullableString($payload['name'] ?? null),
            websiteUrl: self::nullableString($payload['siteWeb'] ?? null),
            phoneNumber: self::nullableString($payload['phone'] ?? null),
            emailAddress: self::nullableString($payload['mail'] ?? null),
            address: self::nullableString($payload['address'] ?? null),
            commune: self::nullableString($payload['commune'] ?? null),
            distance: is_numeric($payload['distance'] ?? null) ? (int) $payload['distance'] : null,
            isAvip: is_bool($payload['isAvip'] ?? null) ? $payload['isAvip'] : null,
            rawPayload: $payload,
            additionalEmails: self::stringList($payload['additionalEmails'] ?? []),
            additionalPhones: self::stringList($payload['additionalPhones'] ?? []),
            additionalAddresses: self::stringList($payload['additionalAddresses'] ?? []),
        );
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_filter(array_map(self::nullableString(...), $values), static fn (?string $value): bool => null !== $value));
    }

    private static function nullableString(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }
}
