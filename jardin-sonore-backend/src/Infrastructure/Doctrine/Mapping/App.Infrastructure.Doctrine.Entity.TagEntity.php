<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\DirectoryEntryEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'tag',
        'indexes' => [
            'idx_tag_label' => ['columns' => ['label']],
        ],
        'uniqueConstraints' => [
            'uniq_tag_uuid' => ['columns' => ['uuid']],
            'uniq_tag_label' => ['columns' => ['label']],
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
        'fieldName' => 'label',
        'type' => Types::STRING,
        'length' => 255,
        'unique' => true,
    ]);

    $metadata->mapManyToMany([
        'fieldName' => 'directoryEntries',
        'targetEntity' => DirectoryEntryEntity::class,
        'mappedBy' => 'tags',
    ]);
};
