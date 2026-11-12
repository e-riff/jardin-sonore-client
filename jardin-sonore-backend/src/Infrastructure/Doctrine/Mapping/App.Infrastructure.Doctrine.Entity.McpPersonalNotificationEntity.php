<?php

declare(strict_types=1);

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'mcp_personal_notification',
        'uniqueConstraints' => ['uniq_mcp_notification_request' => ['columns' => ['request_id']]],
        'indexes' => ['idx_mcp_notification_created' => ['columns' => ['created_at']]],
    ]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapField(['fieldName' => 'requestId', 'columnName' => 'request_id', 'type' => Types::STRING, 'length' => 128]);
    $metadata->mapField(['fieldName' => 'fingerprint', 'type' => Types::STRING, 'length' => 64]);
    $metadata->mapField(['fieldName' => 'state', 'type' => Types::STRING, 'length' => 16]);
    $metadata->mapField(['fieldName' => 'createdAt', 'columnName' => 'created_at', 'type' => Types::DATETIME_IMMUTABLE]);
    $metadata->mapField(['fieldName' => 'finishedAt', 'columnName' => 'finished_at', 'type' => Types::DATETIME_IMMUTABLE, 'nullable' => true]);
};
