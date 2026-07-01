<?php

declare(strict_types=1);

use App\Domain\Model\AddressBook\PhoneContactType;
use App\Infrastructure\Doctrine\Entity\ContactDetailsEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'contact_details_phone_link',
        'indexes' => [
            'idx_contact_details_phone_link_contact_details' => ['columns' => ['contact_details_id']],
            'idx_contact_details_phone_link_phone_contact' => ['columns' => ['phone_contact_id']],
        ],
        'uniqueConstraints' => [
            'uniq_contact_details_phone_link_uuid' => ['columns' => ['uuid']],
            'uniq_contact_details_phone_link_pair' => ['columns' => ['contact_details_id', 'phone_contact_id']],
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
        'fieldName' => 'label',
        'type' => Types::STRING,
        'length' => 255,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'type',
        'type' => Types::STRING,
        'length' => 32,
        'enumType' => PhoneContactType::class,
    ]);

    $metadata->mapField([
        'fieldName' => 'active',
        'type' => Types::BOOLEAN,
        'options' => ['default' => true],
    ]);

    $metadata->mapManyToOne([
        'fieldName' => 'contactDetails',
        'targetEntity' => ContactDetailsEntity::class,
        'inversedBy' => 'phoneContactLinks',
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
        'fieldName' => 'phoneContact',
        'targetEntity' => PhoneContactEntity::class,
        'inversedBy' => 'phoneContactLinks',
        'cascade' => ['persist'],
        'joinColumns' => [
            [
                'name' => 'phone_contact_id',
                'referencedColumnName' => 'id',
                'nullable' => false,
                'onDelete' => 'CASCADE',
            ],
        ],
    ]);
};
