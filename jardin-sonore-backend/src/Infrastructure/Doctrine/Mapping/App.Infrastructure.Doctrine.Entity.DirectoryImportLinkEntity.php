<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\DirectoryEntryEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'directory_import_link',
        'indexes' => [
            'idx_directory_import_link_directory_entry' => ['columns' => ['directory_entry_id']],
            'idx_directory_import_link_source' => ['columns' => ['source']],
        ],
        'uniqueConstraints' => [
            'uniq_directory_import_link_uuid' => ['columns' => ['uuid']],
            'uniq_directory_import_link_source_external_id' => ['columns' => ['source', 'external_id']],
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
        'fieldName' => 'source',
        'type' => Types::STRING,
        'length' => 64,
    ]);

    $metadata->mapField([
        'fieldName' => 'externalId',
        'columnName' => 'external_id',
        'type' => Types::STRING,
        'length' => 255,
    ]);

    $metadata->mapField([
        'fieldName' => 'externalOrganizationId',
        'columnName' => 'external_organization_id',
        'type' => Types::STRING,
        'length' => 255,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'payloadHash',
        'columnName' => 'payload_hash',
        'type' => Types::STRING,
        'length' => 64,
    ]);

    $metadata->mapManyToOne([
        'fieldName' => 'directoryEntry',
        'targetEntity' => DirectoryEntryEntity::class,
        'joinColumns' => [
            [
                'name' => 'directory_entry_id',
                'referencedColumnName' => 'id',
                'nullable' => false,
                'onDelete' => 'CASCADE',
            ],
        ],
    ]);
};
