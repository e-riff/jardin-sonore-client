<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Entity;

use Symfony\Component\Uid\Uuid;

class MailingDeliveryRecipientOrganizationEntity
{
    private ?int $id = null;

    public function __construct(
        private MailingDeliveryRecipientEntity $deliveryRecipient,
        private Uuid $organizationUuid,
        private string $organizationName,
        private ?string $organizationType,
        private ?string $municipalityInseeCode,
    ) {
    }
}
