<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'commercial_request',
        'indexes' => [
            'idx_commercial_request_status_received' => ['columns' => ['status', 'received_at']],
        ],
        'uniqueConstraints' => [
            'uniq_commercial_request_submission_key' => ['columns' => ['submission_key']],
        ],
    ]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapField(['fieldName' => 'source', 'type' => Types::STRING, 'length' => 32]);
    $metadata->mapField(['fieldName' => 'senderName', 'columnName' => 'sender_name', 'type' => Types::STRING, 'length' => 255]);
    $metadata->mapField(['fieldName' => 'emailAddress', 'columnName' => 'email_address', 'type' => Types::STRING, 'length' => 255]);
    $metadata->mapField(['fieldName' => 'message', 'type' => Types::TEXT]);
    $metadata->mapField(['fieldName' => 'receivedAt', 'columnName' => 'received_at', 'type' => Types::DATETIME_IMMUTABLE]);
    $metadata->mapField(['fieldName' => 'organizationName', 'columnName' => 'organization_name', 'type' => Types::STRING, 'length' => 255, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'city', 'type' => Types::STRING, 'length' => 255, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'phone', 'type' => Types::STRING, 'length' => 64, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'submissionKey', 'columnName' => 'submission_key', 'type' => Types::STRING, 'length' => 64, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'status', 'type' => Types::STRING, 'length' => 32]);
    $metadata->mapManyToOne([
        'fieldName' => 'organization',
        'targetEntity' => OrganizationEntity::class,
        'joinColumns' => [['name' => 'organization_id', 'referencedColumnName' => 'id', 'nullable' => true, 'onDelete' => 'RESTRICT']],
    ]);
    $metadata->mapManyToOne([
        'fieldName' => 'person',
        'targetEntity' => PersonEntity::class,
        'joinColumns' => [['name' => 'person_id', 'referencedColumnName' => 'id', 'nullable' => true, 'onDelete' => 'RESTRICT']],
    ]);
    $metadata->mapOneToMany([
        'fieldName' => 'projects',
        'targetEntity' => CommercialProjectEntity::class,
        'mappedBy' => 'request',
        'cascade' => ['persist'],
    ]);
};
