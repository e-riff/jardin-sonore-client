<?php

declare(strict_types=1);

use App\Domain\Model\AddressBook\CustomerStatus;
use App\Domain\Model\AddressBook\DirectoryEntryType;
use App\Infrastructure\Doctrine\Entity\ContactDetailsEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use App\Infrastructure\Doctrine\Entity\TagEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'directory_entry',
        'indexes' => [
            'idx_directory_entry_type' => ['columns' => ['entry_type']],
            'idx_directory_entry_customer_status' => ['columns' => ['customer_status']],
        ],
        'uniqueConstraints' => [
            'uniq_directory_entry_uuid' => ['columns' => ['uuid']],
        ],
    ]);

    $metadata->setInheritanceType(ClassMetadata::INHERITANCE_TYPE_JOINED);
    $metadata->setDiscriminatorColumn([
        'name' => 'discriminator',
        'type' => Types::STRING,
        'length' => 32,
    ]);
    $metadata->setDiscriminatorMap([
        DirectoryEntryType::ORGANIZATION->value => OrganizationEntity::class,
        DirectoryEntryType::PERSON->value => PersonEntity::class,
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
        'fieldName' => 'entryType',
        'columnName' => 'entry_type',
        'type' => Types::STRING,
        'length' => 32,
        'enumType' => DirectoryEntryType::class,
    ]);

    $metadata->mapField([
        'fieldName' => 'customerStatus',
        'columnName' => 'customer_status',
        'type' => Types::STRING,
        'length' => 32,
        'nullable' => true,
        'enumType' => CustomerStatus::class,
    ]);

    $metadata->mapField([
        'fieldName' => 'active',
        'type' => Types::BOOLEAN,
        'options' => ['default' => true],
    ]);

    $metadata->mapOneToOne([
        'fieldName' => 'contactDetails',
        'targetEntity' => ContactDetailsEntity::class,
        'mappedBy' => 'directoryEntry',
        'cascade' => ['persist'],
    ]);

    $metadata->mapManyToMany([
        'fieldName' => 'tags',
        'targetEntity' => TagEntity::class,
        'inversedBy' => 'directoryEntries',
        'joinTable' => [
            'name' => 'directory_entry_tag',
            'joinColumns' => [
                [
                    'name' => 'directory_entry_id',
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
