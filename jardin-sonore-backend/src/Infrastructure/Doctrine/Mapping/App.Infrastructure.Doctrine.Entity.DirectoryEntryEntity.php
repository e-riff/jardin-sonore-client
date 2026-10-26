<?php

declare(strict_types=1);

use App\Domain\Model\AddressBook\DirectoryEntryType;
use App\Infrastructure\Doctrine\Entity\ContactDetailsEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'directory_entry',
        'indexes' => [
            'idx_directory_entry_type' => ['columns' => ['entry_type']],
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
        'fieldName' => 'active',
        'type' => Types::BOOLEAN,
        'options' => ['default' => true],
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
        'fieldName' => 'contactDetails',
        'targetEntity' => ContactDetailsEntity::class,
        'mappedBy' => 'directoryEntry',
        'cascade' => ['persist'],
    ]);
};
