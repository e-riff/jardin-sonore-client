<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'phone_contact',
        'indexes' => [
            'idx_phone_contact_organization' => ['columns' => ['organization_id']],
            'idx_phone_contact_person' => ['columns' => ['person_id']],
            'idx_phone_contact_phone_number' => ['columns' => ['phone_number']],
        ],
        'uniqueConstraints' => [
            'uniq_phone_contact_uuid' => ['columns' => ['uuid']],
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
        'fieldName' => 'phoneNumber',
        'columnName' => 'phone_number',
        'type' => Types::STRING,
        'length' => 20,
    ]);

    $metadata->mapField([
        'fieldName' => 'label',
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
        'fieldName' => 'organization',
        'targetEntity' => OrganizationEntity::class,
        'inversedBy' => 'phoneContacts',
        'joinColumns' => [
            [
                'name' => 'organization_id',
                'referencedColumnName' => 'id',
                'nullable' => true,
                'onDelete' => 'SET NULL',
            ],
        ],
    ]);

    $metadata->mapManyToOne([
        'fieldName' => 'person',
        'targetEntity' => PersonEntity::class,
        'inversedBy' => 'phoneContacts',
        'joinColumns' => [
            [
                'name' => 'person_id',
                'referencedColumnName' => 'id',
                'nullable' => true,
                'onDelete' => 'SET NULL',
            ],
        ],
    ]);
};
