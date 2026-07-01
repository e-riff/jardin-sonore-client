<?php

declare(strict_types=1);

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'mailing_delivery_recipient',
        'indexes' => [
            'idx_mailing_delivery_campaign_status' => ['columns' => ['campaign_uuid', 'status']],
            'idx_mailing_delivery_dispatched_at' => ['columns' => ['dispatched_at']],
        ],
        'uniqueConstraints' => [
            'uniq_mailing_delivery_campaign_email' => ['columns' => ['campaign_uuid', 'email_address']],
        ],
    ]);

    $metadata->mapField([
        'fieldName' => 'id',
        'type' => Types::BIGINT,
        'id' => true,
    ]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);

    $metadata->mapField([
        'fieldName' => 'campaignUuid',
        'columnName' => 'campaign_uuid',
        'type' => Types::STRING,
        'length' => 36,
    ]);

    $metadata->mapField([
        'fieldName' => 'emailAddress',
        'columnName' => 'email_address',
        'type' => Types::STRING,
        'length' => 255,
    ]);

    $metadata->mapField([
        'fieldName' => 'unsubscribeToken',
        'columnName' => 'unsubscribe_token',
        'type' => Types::STRING,
        'length' => 64,
    ]);

    $metadata->mapField([
        'fieldName' => 'displayName',
        'columnName' => 'display_name',
        'type' => Types::STRING,
        'length' => 255,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'status',
        'type' => Types::STRING,
        'length' => 32,
    ]);

    $metadata->mapField([
        'fieldName' => 'queuedAt',
        'columnName' => 'queued_at',
        'type' => Types::DATETIME_IMMUTABLE,
    ]);

    $metadata->mapField([
        'fieldName' => 'dispatchedAt',
        'columnName' => 'dispatched_at',
        'type' => Types::DATETIME_IMMUTABLE,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'sentAt',
        'columnName' => 'sent_at',
        'type' => Types::DATETIME_IMMUTABLE,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'failedAt',
        'columnName' => 'failed_at',
        'type' => Types::DATETIME_IMMUTABLE,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'updatedAt',
        'columnName' => 'updated_at',
        'type' => Types::DATETIME_IMMUTABLE,
    ]);

    $metadata->mapField([
        'fieldName' => 'lastError',
        'columnName' => 'last_error',
        'type' => Types::TEXT,
        'nullable' => true,
    ]);
};
