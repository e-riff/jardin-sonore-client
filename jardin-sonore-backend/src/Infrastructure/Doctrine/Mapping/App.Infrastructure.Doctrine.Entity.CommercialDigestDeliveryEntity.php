<?php

declare(strict_types=1);

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'commercial_digest_delivery',
        'uniqueConstraints' => ['uniq_commercial_digest_local_date' => ['columns' => ['local_date']]],
    ]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapField(['fieldName' => 'localDate', 'columnName' => 'local_date', 'type' => Types::DATE_IMMUTABLE]);
    $metadata->mapField(['fieldName' => 'createdAt', 'columnName' => 'created_at', 'type' => Types::DATETIME_IMMUTABLE]);
    $metadata->mapField(['fieldName' => 'status', 'type' => Types::STRING, 'length' => 16]);
    $metadata->mapField(['fieldName' => 'attempts', 'type' => Types::INTEGER]);
    $metadata->mapField(['fieldName' => 'sentAt', 'columnName' => 'sent_at', 'type' => Types::DATETIME_IMMUTABLE, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'lastError', 'columnName' => 'last_error', 'type' => Types::STRING, 'length' => 255, 'nullable' => true]);
};
