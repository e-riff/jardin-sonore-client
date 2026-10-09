<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\CommercialRequestEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'commercial_contact_delivery',
        'indexes' => [
            'idx_commercial_contact_delivery_status' => ['columns' => ['status']],
        ],
        'uniqueConstraints' => [
            'uniq_commercial_contact_delivery_request' => ['columns' => ['request_id']],
        ],
    ]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapField(['fieldName' => 'payloadHash', 'columnName' => 'payload_hash', 'type' => Types::STRING, 'length' => 64]);
    $metadata->mapField(['fieldName' => 'status', 'type' => Types::STRING, 'length' => 16]);
    $metadata->mapField(['fieldName' => 'createdAt', 'columnName' => 'created_at', 'type' => Types::DATETIME_IMMUTABLE]);
    $metadata->mapField(['fieldName' => 'sentAt', 'columnName' => 'sent_at', 'type' => Types::DATETIME_IMMUTABLE, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'attempts', 'type' => Types::INTEGER, 'options' => ['default' => 0]]);
    $metadata->mapField(['fieldName' => 'lastError', 'columnName' => 'last_error', 'type' => Types::TEXT, 'nullable' => true]);
    $metadata->mapOneToOne([
        'fieldName' => 'request',
        'targetEntity' => CommercialRequestEntity::class,
        'joinColumns' => [['name' => 'request_id', 'referencedColumnName' => 'id', 'nullable' => false, 'onDelete' => 'RESTRICT']],
    ]);
};
