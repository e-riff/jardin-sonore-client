<?php

declare(strict_types=1);

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'newsletter_recommendation',
        'indexes' => [
            'idx_newsletter_recommendation_title' => ['columns' => ['title']],
            'idx_newsletter_recommendation_active' => ['columns' => ['active']],
        ],
        'uniqueConstraints' => [
            'uniq_newsletter_recommendation_uuid' => ['columns' => ['uuid']],
        ],
    ]);

    $metadata->mapField([
        'fieldName' => 'id',
        'type' => Types::INTEGER,
        'id' => true,
    ]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);

    $metadata->mapField([
        'fieldName' => 'uuid',
        'type' => UuidType::NAME,
        'unique' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'title',
        'type' => Types::STRING,
        'length' => 255,
    ]);

    $metadata->mapField([
        'fieldName' => 'tag',
        'type' => Types::STRING,
        'length' => 40,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'text',
        'type' => Types::TEXT,
    ]);

    $metadata->mapField([
        'fieldName' => 'url',
        'type' => Types::STRING,
        'length' => 2048,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'linkLabel',
        'columnName' => 'link_label',
        'type' => Types::STRING,
        'length' => 255,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'imagePath',
        'columnName' => 'image_path',
        'type' => Types::STRING,
        'length' => 255,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'active',
        'type' => Types::BOOLEAN,
        'options' => ['default' => true],
    ]);

    $metadata->mapField([
        'fieldName' => 'createdAt',
        'columnName' => 'created_at',
        'type' => Types::DATETIME_IMMUTABLE,
    ]);

    $metadata->mapField([
        'fieldName' => 'updatedAt',
        'columnName' => 'updated_at',
        'type' => Types::DATETIME_IMMUTABLE,
    ]);
};
