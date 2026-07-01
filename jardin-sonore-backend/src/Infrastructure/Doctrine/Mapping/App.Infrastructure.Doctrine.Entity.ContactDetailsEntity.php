<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\AddressContactEntity;
use App\Infrastructure\Doctrine\Entity\DirectoryEntryEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactLinkEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactLinkEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'contact_details',
        'indexes' => [
            'idx_contact_details_directory_entry' => ['columns' => ['directory_entry_id']],
        ],
        'uniqueConstraints' => [
            'uniq_contact_details_uuid' => ['columns' => ['uuid']],
            'uniq_contact_details_directory_entry' => ['columns' => ['directory_entry_id']],
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

    $metadata->mapOneToOne([
        'fieldName' => 'directoryEntry',
        'targetEntity' => DirectoryEntryEntity::class,
        'inversedBy' => 'contactDetails',
        'joinColumns' => [
            [
                'name' => 'directory_entry_id',
                'referencedColumnName' => 'id',
                'nullable' => false,
                'unique' => true,
                'onDelete' => 'CASCADE',
            ],
        ],
    ]);

    $metadata->mapOneToMany([
        'fieldName' => 'emailContactLinks',
        'targetEntity' => EmailContactLinkEntity::class,
        'mappedBy' => 'contactDetails',
        'cascade' => ['persist'],
        'orphanRemoval' => true,
    ]);

    $metadata->mapOneToMany([
        'fieldName' => 'phoneContactLinks',
        'targetEntity' => PhoneContactLinkEntity::class,
        'mappedBy' => 'contactDetails',
        'cascade' => ['persist'],
        'orphanRemoval' => true,
    ]);

    $metadata->mapOneToMany([
        'fieldName' => 'addressContacts',
        'targetEntity' => AddressContactEntity::class,
        'mappedBy' => 'contactDetails',
        'cascade' => ['persist'],
        'orphanRemoval' => true,
    ]);
};
