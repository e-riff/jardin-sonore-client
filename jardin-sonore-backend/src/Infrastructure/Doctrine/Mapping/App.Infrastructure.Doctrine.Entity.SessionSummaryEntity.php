<?php

declare(strict_types=1);

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'session_summary',
        'indexes' => [
            'idx_session_summary_date' => ['columns' => ['session_date']],
            'idx_session_summary_organization' => ['columns' => ['organization_name']],
        ],
        'uniqueConstraints' => [
            'uniq_session_summary_uuid' => ['columns' => ['uuid']],
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
        'fieldName' => 'sessionDate',
        'columnName' => 'session_date',
        'type' => Types::DATE_IMMUTABLE,
    ]);

    $metadata->mapField([
        'fieldName' => 'organizationName',
        'columnName' => 'organization_name',
        'type' => Types::STRING,
        'length' => 255,
    ]);

    $metadata->mapField([
        'fieldName' => 'theme',
        'type' => Types::STRING,
        'length' => 255,
        'nullable' => true,
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
};
