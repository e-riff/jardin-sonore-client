<?php

declare(strict_types=1);

use App\Domain\Model\AddressBook\PhoneContactType;
use App\Infrastructure\Doctrine\Entity\ContactDetailsEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'phone_contact',
        'indexes' => [
            'idx_phone_contact_details' => ['columns' => ['contact_details_id']],
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
        'length' => 32,
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
        'inversedBy' => 'phoneContacts',
        'joinColumns' => [
            [
                'name' => 'contact_details_id',
                'referencedColumnName' => 'id',
                'nullable' => false,
                'onDelete' => 'CASCADE',
            ],
        ],
    ]);
};
