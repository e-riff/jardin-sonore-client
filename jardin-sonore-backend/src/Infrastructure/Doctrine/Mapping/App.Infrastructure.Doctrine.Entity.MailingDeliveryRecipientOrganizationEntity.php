<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\MailingDeliveryRecipientEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'mailing_delivery_recipient_organization',
        'indexes' => ['idx_mailing_recipient_organization_uuid' => ['columns' => ['organization_uuid']]],
        'uniqueConstraints' => ['uniq_mailing_recipient_organization' => ['columns' => ['delivery_recipient_id', 'organization_uuid']]],
    ]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::BIGINT, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapManyToOne([
        'fieldName' => 'deliveryRecipient',
        'targetEntity' => MailingDeliveryRecipientEntity::class,
        'joinColumns' => [['name' => 'delivery_recipient_id', 'referencedColumnName' => 'id', 'nullable' => false, 'onDelete' => 'CASCADE']],
    ]);
    $metadata->mapField(['fieldName' => 'organizationUuid', 'columnName' => 'organization_uuid', 'type' => UuidType::NAME]);
    $metadata->mapField(['fieldName' => 'organizationName', 'columnName' => 'organization_name', 'type' => Types::STRING, 'length' => 255]);
    $metadata->mapField(['fieldName' => 'organizationType', 'columnName' => 'organization_type', 'type' => Types::STRING, 'length' => 32, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'municipalityInseeCode', 'columnName' => 'municipality_insee_code', 'type' => Types::STRING, 'length' => 5, 'nullable' => true]);
};
