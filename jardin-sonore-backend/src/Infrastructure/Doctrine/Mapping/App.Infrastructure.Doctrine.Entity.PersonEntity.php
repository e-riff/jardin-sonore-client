<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'person',
        'indexes' => [
            'idx_person_organization' => ['columns' => ['organization_id']],
            'idx_person_name' => ['columns' => ['last_name', 'first_name']],
        ],
    ]);

    $metadata->mapField([
        'fieldName' => 'firstName',
        'columnName' => 'first_name',
        'type' => Types::STRING,
        'length' => 255,
    ]);

    $metadata->mapField([
        'fieldName' => 'lastName',
        'columnName' => 'last_name',
        'type' => Types::STRING,
        'length' => 255,
    ]);

    $metadata->mapField([
        'fieldName' => 'role',
        'type' => Types::STRING,
        'length' => 255,
        'nullable' => true,
    ]);

    $metadata->mapManyToOne([
        'fieldName' => 'organization',
        'targetEntity' => OrganizationEntity::class,
        'inversedBy' => 'people',
        'joinColumns' => [
            [
                'name' => 'organization_id',
                'referencedColumnName' => 'id',
                'nullable' => true,
                'onDelete' => 'SET NULL',
            ],
        ],
    ]);
};
