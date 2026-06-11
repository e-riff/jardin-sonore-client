<?php

declare(strict_types=1);

use App\Domain\Model\AddressBook\CustomerStatus;
use App\Domain\Model\AddressBook\OrganizationSector;
use App\Domain\Model\AddressBook\OrganizationType;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;
use App\Infrastructure\Doctrine\Entity\TagEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'organization',
        'indexes' => [
            'idx_organization_municipality' => ['columns' => ['municipality_id']],
            'idx_organization_name' => ['columns' => ['name']],
            'idx_organization_customer_status' => ['columns' => ['customer_status']],
        ],
        'uniqueConstraints' => [
            'uniq_organization_uuid' => ['columns' => ['uuid']],
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
        'fieldName' => 'type',
        'type' => Types::STRING,
        'length' => 32,
        'enumType' => OrganizationType::class,
    ]);

    $metadata->mapField([
        'fieldName' => 'sector',
        'type' => Types::STRING,
        'length' => 32,
        'enumType' => OrganizationSector::class,
    ]);

    $metadata->mapField([
        'fieldName' => 'customerStatus',
        'columnName' => 'customer_status',
        'type' => Types::STRING,
        'length' => 32,
        'enumType' => CustomerStatus::class,
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
        'fieldName' => 'city',
        'type' => Types::STRING,
        'length' => 255,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'active',
        'type' => Types::BOOLEAN,
        'options' => ['default' => true],
    ]);

    $metadata->mapManyToOne([
        'fieldName' => 'municipality',
        'targetEntity' => MunicipalityEntity::class,
        'joinColumns' => [
            [
                'name' => 'municipality_id',
                'referencedColumnName' => 'id',
                'nullable' => true,
                'onDelete' => 'SET NULL',
            ],
        ],
    ]);

    $metadata->mapOneToMany([
        'fieldName' => 'people',
        'targetEntity' => PersonEntity::class,
        'mappedBy' => 'organization',
    ]);

    $metadata->mapOneToMany([
        'fieldName' => 'emailContacts',
        'targetEntity' => EmailContactEntity::class,
        'mappedBy' => 'organization',
    ]);

    $metadata->mapOneToMany([
        'fieldName' => 'phoneContacts',
        'targetEntity' => PhoneContactEntity::class,
        'mappedBy' => 'organization',
    ]);

    $metadata->mapManyToMany([
        'fieldName' => 'tags',
        'targetEntity' => TagEntity::class,
        'inversedBy' => 'organizations',
        'joinTable' => [
            'name' => 'organization_tag',
            'joinColumns' => [
                [
                    'name' => 'organization_id',
                    'referencedColumnName' => 'id',
                    'onDelete' => 'CASCADE',
                ],
            ],
            'inverseJoinColumns' => [
                [
                    'name' => 'tag_id',
                    'referencedColumnName' => 'id',
                    'onDelete' => 'CASCADE',
                ],
            ],
        ],
    ]);
};
