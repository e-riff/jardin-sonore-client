<?php

declare(strict_types=1);

use App\Domain\Model\AddressBook\OrganizationSector;
use App\Domain\Model\AddressBook\OrganizationType;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'organization',
        'indexes' => [
            'idx_organization_name' => ['columns' => ['name']],
        ],
    ]);

    $metadata->mapField([
        'fieldName' => 'name',
        'type' => Types::STRING,
        'length' => 255,
    ]);

    $metadata->mapField([
        'fieldName' => 'type',
        'type' => Types::STRING,
        'length' => 32,
        'nullable' => true,
        'enumType' => OrganizationType::class,
    ]);

    $metadata->mapField([
        'fieldName' => 'sector',
        'type' => Types::STRING,
        'length' => 32,
        'nullable' => true,
        'enumType' => OrganizationSector::class,
    ]);

    $metadata->mapField([
        'fieldName' => 'websiteUrl',
        'columnName' => 'website_url',
        'type' => Types::STRING,
        'length' => 2048,
        'nullable' => true,
    ]);

    $metadata->mapOneToMany([
        'fieldName' => 'people',
        'targetEntity' => PersonEntity::class,
        'mappedBy' => 'organization',
    ]);
};
