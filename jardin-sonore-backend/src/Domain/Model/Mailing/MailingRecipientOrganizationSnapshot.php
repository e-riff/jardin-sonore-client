<?php

declare(strict_types=1);

namespace App\Domain\Model\Mailing;

use Symfony\Component\Uid\Uuid;

final readonly class MailingRecipientOrganizationSnapshot
{
    public function __construct(
        public Uuid $organizationUuid,
        public string $organizationName,
        public ?string $organizationType,
        public ?string $municipalityInseeCode,
    ) {
    }
}
