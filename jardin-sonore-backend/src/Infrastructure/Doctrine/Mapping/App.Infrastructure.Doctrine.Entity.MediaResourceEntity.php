<?php

declare(strict_types=1);

use App\Domain\Model\Session\MediaResourceType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'media_resource',
        'indexes' => [
            'idx_media_resource_type' => ['columns' => ['type']],
            'idx_media_resource_active' => ['columns' => ['active']],
        ],
        'uniqueConstraints' => [
            'uniq_media_resource_uuid' => ['columns' => ['uuid']],
        ],
    ]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapField(['fieldName' => 'uuid', 'type' => UuidType::NAME, 'unique' => true]);
    $metadata->mapField(['fieldName' => 'type', 'type' => Types::STRING, 'length' => 32, 'enumType' => MediaResourceType::class]);
    $metadata->mapField(['fieldName' => 'title', 'type' => Types::STRING, 'length' => 255]);
    $metadata->mapField(['fieldName' => 'source', 'type' => Types::STRING, 'length' => 255, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'description', 'type' => Types::TEXT, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'primaryUrl', 'columnName' => 'primary_url', 'type' => Types::STRING, 'length' => 2048]);
    $metadata->mapField(['fieldName' => 'secondaryUrl', 'columnName' => 'secondary_url', 'type' => Types::STRING, 'length' => 2048, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'imageUrl', 'columnName' => 'image_url', 'type' => Types::STRING, 'length' => 2048, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'active', 'type' => Types::BOOLEAN, 'options' => ['default' => true]]);
    $metadata->mapField(['fieldName' => 'createdAt', 'columnName' => 'created_at', 'type' => Types::DATETIME_IMMUTABLE]);
    $metadata->mapField(['fieldName' => 'updatedAt', 'columnName' => 'updated_at', 'type' => Types::DATETIME_IMMUTABLE]);
};
