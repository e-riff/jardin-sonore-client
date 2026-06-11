<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;
use App\Infrastructure\Doctrine\Entity\RegionEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'department',
        'indexes' => [
            'idx_department_region' => ['columns' => ['region_id']],
        ],
        'uniqueConstraints' => [
            'uniq_department_uuid' => ['columns' => ['uuid']],
            'uniq_department_code' => ['columns' => ['code']],
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
        'length' => 150,
    ]);

    $metadata->mapField([
        'fieldName' => 'code',
        'type' => Types::STRING,
        'length' => 3,
        'unique' => true,
    ]);

    $metadata->mapManyToOne([
        'fieldName' => 'region',
        'targetEntity' => RegionEntity::class,
        'inversedBy' => 'departments',
        'joinColumns' => [
            [
                'name' => 'region_id',
                'referencedColumnName' => 'id',
                'nullable' => false,
            ],
        ],
    ]);

    $metadata->mapOneToMany([
        'fieldName' => 'municipalities',
        'targetEntity' => MunicipalityEntity::class,
        'mappedBy' => 'department',
    ]);
};
