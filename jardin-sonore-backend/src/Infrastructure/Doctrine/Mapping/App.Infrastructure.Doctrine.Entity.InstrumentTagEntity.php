<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\InstrumentEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'instrument_tag',
        'indexes' => [
            'idx_instrument_tag_label' => ['columns' => ['label']],
        ],
        'uniqueConstraints' => [
            'uniq_instrument_tag_uuid' => ['columns' => ['uuid']],
            'uniq_instrument_tag_label' => ['columns' => ['label']],
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
        'fieldName' => 'createdAt',
        'columnName' => 'created_at',
        'type' => Types::DATETIME_IMMUTABLE,
        'options' => [
            'gedmo' => [
                'timestampable' => ['on' => 'create'],
            ],
        ],
    ]);

    $metadata->mapField([
        'fieldName' => 'updatedAt',
        'columnName' => 'updated_at',
        'type' => Types::DATETIME_IMMUTABLE,
        'options' => [
            'gedmo' => [
                'timestampable' => ['on' => 'update'],
            ],
        ],
    ]);

    $metadata->mapField([
        'fieldName' => 'label',
        'type' => Types::STRING,
        'length' => 255,
        'unique' => true,
    ]);

    $metadata->mapManyToMany([
        'fieldName' => 'instruments',
        'targetEntity' => InstrumentEntity::class,
        'mappedBy' => 'tags',
    ]);
};
