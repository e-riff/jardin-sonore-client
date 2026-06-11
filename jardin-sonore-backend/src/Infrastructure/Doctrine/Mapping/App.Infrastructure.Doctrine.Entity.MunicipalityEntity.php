<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\DepartmentEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'municipality',
        'indexes' => [
            'idx_municipality_department' => ['columns' => ['department_id']],
            'idx_municipality_postal_code' => ['columns' => ['postal_code']],
        ],
        'uniqueConstraints' => [
            'uniq_municipality_uuid' => ['columns' => ['uuid']],
            'uniq_municipality_insee_code' => ['columns' => ['insee_code']],
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
        'fieldName' => 'phoneNumber',
        'columnName' => 'phone_number',
        'type' => Types::STRING,
        'length' => 20,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'emailAddress',
        'columnName' => 'email_address',
        'type' => Types::STRING,
        'length' => 255,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'address',
        'type' => Types::TEXT,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'postalCode',
        'columnName' => 'postal_code',
        'type' => Types::STRING,
        'length' => 5,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'inseeCode',
        'columnName' => 'insee_code',
        'type' => Types::STRING,
        'length' => 5,
        'nullable' => true,
        'unique' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'siren',
        'type' => Types::STRING,
        'length' => 9,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'siret',
        'type' => Types::STRING,
        'length' => 14,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'geoShape',
        'columnName' => 'geo_shape',
        'type' => Types::JSON,
        'nullable' => true,
    ]);

    $metadata->mapManyToOne([
        'fieldName' => 'department',
        'targetEntity' => DepartmentEntity::class,
        'inversedBy' => 'municipalities',
        'joinColumns' => [
            [
                'name' => 'department_id',
                'referencedColumnName' => 'id',
                'nullable' => false,
            ],
        ],
    ]);
};
