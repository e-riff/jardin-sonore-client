<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'person',
        'indexes' => [
            'idx_person_organization' => ['columns' => ['organization_id']],
            'idx_person_name' => ['columns' => ['last_name', 'first_name']],
        ],
        'uniqueConstraints' => [
            'uniq_person_uuid' => ['columns' => ['uuid']],
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

    $metadata->mapField([
        'fieldName' => 'active',
        'type' => Types::BOOLEAN,
        'options' => ['default' => true],
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

    $metadata->mapOneToMany([
        'fieldName' => 'emailContacts',
        'targetEntity' => EmailContactEntity::class,
        'mappedBy' => 'person',
    ]);

    $metadata->mapOneToMany([
        'fieldName' => 'phoneContacts',
        'targetEntity' => PhoneContactEntity::class,
        'mappedBy' => 'person',
    ]);
};
