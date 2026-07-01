<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\PhoneContactLinkEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'phone_contact',
        'indexes' => [
            'idx_phone_contact_phone_number' => ['columns' => ['phone_number']],
        ],
        'uniqueConstraints' => [
            'uniq_phone_contact_uuid' => ['columns' => ['uuid']],
            'uniq_phone_contact_phone_number' => ['columns' => ['phone_number']],
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
        'fieldName' => 'createdAt',
        'columnName' => 'created_at',
        'type' => Types::DATETIME_IMMUTABLE,
        'options' => [
            'gedmo' => [
                'timestampable' => ['on' => 'create'],
            ],
        ],
    ]);

    $metadata->mapField([
        'fieldName' => 'updatedAt',
        'columnName' => 'updated_at',
        'type' => Types::DATETIME_IMMUTABLE,
        'options' => [
            'gedmo' => [
                'timestampable' => ['on' => 'update'],
            ],
        ],
    ]);

    $metadata->mapField([
        'fieldName' => 'phoneNumber',
        'columnName' => 'phone_number',
        'type' => Types::STRING,
        'length' => 32,
    ]);

    $metadata->mapField([
        'fieldName' => 'active',
        'type' => Types::BOOLEAN,
        'options' => ['default' => true],
    ]);

    $metadata->mapOneToMany([
        'fieldName' => 'phoneContactLinks',
        'targetEntity' => PhoneContactLinkEntity::class,
        'mappedBy' => 'phoneContact',
    ]);
};
