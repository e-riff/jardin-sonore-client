<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\DepartmentEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'region',
        'uniqueConstraints' => [
            'uniq_region_uuid' => ['columns' => ['uuid']],
            'uniq_region_code' => ['columns' => ['code']],
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

    $metadata->mapOneToMany([
        'fieldName' => 'departments',
        'targetEntity' => DepartmentEntity::class,
        'mappedBy' => 'region',
    ]);
};
