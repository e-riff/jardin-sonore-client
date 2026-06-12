<?php

declare(strict_types=1);

use App\Domain\Model\AddressBook\AddressContactType;
use App\Infrastructure\Doctrine\Entity\ContactDetailsEntity;
use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'address_contact',
        'indexes' => [
            'idx_address_contact_details' => ['columns' => ['contact_details_id']],
            'idx_address_contact_municipality' => ['columns' => ['municipality_id']],
            'idx_address_contact_postal_code' => ['columns' => ['postal_code']],
        ],
        'uniqueConstraints' => [
            'uniq_address_contact_uuid' => ['columns' => ['uuid']],
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
        'fieldName' => 'type',
        'type' => Types::STRING,
        'length' => 32,
        'enumType' => AddressContactType::class,
    ]);

    $metadata->mapField([
        'fieldName' => 'label',
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
        'fieldName' => 'contactDetails',
        'targetEntity' => ContactDetailsEntity::class,
        'inversedBy' => 'addressContacts',
        'joinColumns' => [
            [
                'name' => 'contact_details_id',
                'referencedColumnName' => 'id',
                'nullable' => false,
                'onDelete' => 'CASCADE',
            ],
        ],
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
};
