<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\InstrumentTagEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'instrument',
        'indexes' => [
            'idx_instrument_name' => ['columns' => ['name']],
            'idx_instrument_active' => ['columns' => ['active']],
            'idx_instrument_updated_at' => ['columns' => ['updated_at']],
        ],
        'uniqueConstraints' => [
            'uniq_instrument_uuid' => ['columns' => ['uuid']],
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
        'fieldName' => 'name',
        'type' => Types::STRING,
        'length' => 255,
    ]);

    $metadata->mapField([
        'fieldName' => 'tuning',
        'type' => Types::STRING,
        'length' => 80,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'quantity',
        'type' => Types::INTEGER,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'notes',
        'type' => Types::TEXT,
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

    $metadata->mapManyToMany([
        'fieldName' => 'tags',
        'targetEntity' => InstrumentTagEntity::class,
        'inversedBy' => 'instruments',
        'joinTable' => [
            'name' => 'instrument_instrument_tag',
            'joinColumns' => [
                [
                    'name' => 'instrument_id',
                    'referencedColumnName' => 'id',
                    'nullable' => false,
                    'onDelete' => 'CASCADE',
                ],
            ],
            'inverseJoinColumns' => [
                [
                    'name' => 'instrument_tag_id',
                    'referencedColumnName' => 'id',
                    'nullable' => false,
                    'onDelete' => 'CASCADE',
                ],
            ],
        ],
    ]);
};
