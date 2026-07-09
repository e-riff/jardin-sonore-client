<?php

declare(strict_types=1);

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'mailing_audience_mask',
        'indexes' => [
            'idx_mailing_audience_mask_name' => ['columns' => ['name']],
            'idx_mailing_audience_mask_updated_at' => ['columns' => ['updated_at']],
        ],
        'uniqueConstraints' => [
            'uniq_mailing_audience_mask_uuid' => ['columns' => ['uuid']],
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
        'fieldName' => 'audienceFilter',
        'columnName' => 'audience_filter',
        'type' => Types::JSON,
    ]);

    $metadata->mapField([
        'fieldName' => 'materializedMunicipalityInseeCodes',
        'columnName' => 'materialized_municipality_insee_codes',
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
