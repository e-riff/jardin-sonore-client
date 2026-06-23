<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\AddressContactEntity;
use App\Infrastructure\Doctrine\Entity\DirectoryEntryEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;
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
        'fieldName' => 'emailContacts',
        'targetEntity' => EmailContactEntity::class,
        'mappedBy' => 'contactDetails',
        'cascade' => ['persist'],
        'orphanRemoval' => true,
    ]);

    $metadata->mapOneToMany([
        'fieldName' => 'phoneContacts',
        'targetEntity' => PhoneContactEntity::class,
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
