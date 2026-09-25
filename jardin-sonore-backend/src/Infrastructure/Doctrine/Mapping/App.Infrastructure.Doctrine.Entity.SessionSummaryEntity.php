<?php

declare(strict_types=1);

use App\Domain\Model\Session\SessionDocumentStatus;
use App\Infrastructure\Doctrine\Entity\SessionSummaryOrganizationEntity;
use App\Infrastructure\Doctrine\Entity\ThemeEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'session_summary',
        'indexes' => [
            'idx_session_summary_date' => ['columns' => ['session_date']],
        ],
        'uniqueConstraints' => [
            'uniq_session_summary_uuid' => ['columns' => ['uuid']],
            'uniq_session_summary_slug' => ['columns' => ['slug']],
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
        'fieldName' => 'slug',
        'type' => Types::STRING,
        'length' => 255,
    ]);

    $metadata->mapField([
        'fieldName' => 'sessionDate',
        'columnName' => 'session_date',
        'type' => Types::DATE_IMMUTABLE,
    ]);

    $metadata->mapOneToMany([
        'fieldName' => 'organizationShares',
        'targetEntity' => SessionSummaryOrganizationEntity::class,
        'mappedBy' => 'sessionSummary',
        'cascade' => ['persist', 'remove'],
        'orphanRemoval' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'theme',
        'type' => Types::STRING,
        'length' => 255,
        'nullable' => true,
    ]);
    $metadata->mapManyToMany([
        'fieldName' => 'themes',
        'targetEntity' => ThemeEntity::class,
        'joinTable' => [
            'name' => 'session_summary_theme',
            'joinColumns' => [['name' => 'session_summary_id', 'referencedColumnName' => 'id', 'onDelete' => 'CASCADE']],
            'inverseJoinColumns' => [['name' => 'theme_id', 'referencedColumnName' => 'id', 'onDelete' => 'CASCADE']],
        ],
    ]);

    $metadata->mapField([
        'fieldName' => 'generalNotes',
        'columnName' => 'general_notes',
        'type' => Types::TEXT,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'materialSummary',
        'columnName' => 'material_summary',
        'type' => Types::TEXT,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'furtherExploration',
        'columnName' => 'further_exploration',
        'type' => Types::TEXT,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'instrumentUuids',
        'columnName' => 'instrument_uuids',
        'type' => Types::JSON,
    ]);
    $metadata->mapField([
        'fieldName' => 'recommendationUuids',
        'columnName' => 'recommendation_uuids',
        'type' => Types::JSON,
    ]);

    $metadata->mapField([
        'fieldName' => 'sequences',
        'type' => Types::JSON,
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

    $metadata->mapField([
        'fieldName' => 'documentStatus',
        'columnName' => 'document_status',
        'type' => Types::STRING,
        'length' => 20,
        'enumType' => SessionDocumentStatus::class,
        'options' => ['default' => SessionDocumentStatus::PENDING->value],
    ]);
    $metadata->mapField([
        'fieldName' => 'documentPath',
        'columnName' => 'document_path',
        'type' => Types::STRING,
        'length' => 500,
        'nullable' => true,
    ]);
    $metadata->mapField([
        'fieldName' => 'documentError',
        'columnName' => 'document_error',
        'type' => Types::TEXT,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'published',
        'type' => Types::BOOLEAN,
        'options' => ['default' => false],
    ]);
};
