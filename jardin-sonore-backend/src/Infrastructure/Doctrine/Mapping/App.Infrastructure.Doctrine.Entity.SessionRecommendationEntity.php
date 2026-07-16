<?php

declare(strict_types=1);

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'session_recommendation',
        'indexes' => [
            'idx_session_recommendation_active' => ['columns' => ['active']],
        ],
        'uniqueConstraints' => [
            'uniq_session_recommendation_uuid' => ['columns' => ['uuid']],
        ],
    ]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapField(['fieldName' => 'uuid', 'type' => UuidType::NAME, 'unique' => true]);
    $metadata->mapField(['fieldName' => 'title', 'type' => Types::STRING, 'length' => 255]);
    $metadata->mapField(['fieldName' => 'text', 'type' => Types::TEXT]);
    $metadata->mapField(['fieldName' => 'notes', 'type' => Types::TEXT, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'primaryUrl', 'columnName' => 'primary_url', 'type' => Types::STRING, 'length' => 2048, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'secondaryUrl', 'columnName' => 'secondary_url', 'type' => Types::STRING, 'length' => 2048, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'imageUrl', 'columnName' => 'image_url', 'type' => Types::STRING, 'length' => 2048, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'active', 'type' => Types::BOOLEAN, 'options' => ['default' => true]]);
    $metadata->mapField(['fieldName' => 'createdAt', 'columnName' => 'created_at', 'type' => Types::DATETIME_IMMUTABLE]);
    $metadata->mapField(['fieldName' => 'updatedAt', 'columnName' => 'updated_at', 'type' => Types::DATETIME_IMMUTABLE]);
};
