<?php

declare(strict_types=1);

use App\Domain\Model\AddressBook\ContactDataSource;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'email_contact',
        'indexes' => [
            'idx_email_contact_organization' => ['columns' => ['organization_id']],
            'idx_email_contact_person' => ['columns' => ['person_id']],
            'idx_email_contact_newsletter' => ['columns' => ['active', 'opt_in_newsletter']],
        ],
        'uniqueConstraints' => [
            'uniq_email_contact_uuid' => ['columns' => ['uuid']],
            'uniq_email_contact_email_address' => ['columns' => ['email_address']],
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
        'fieldName' => 'emailAddress',
        'columnName' => 'email_address',
        'type' => Types::STRING,
        'length' => 255,
        'unique' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'label',
        'type' => Types::STRING,
        'length' => 255,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'optInNewsletter',
        'columnName' => 'opt_in_newsletter',
        'type' => Types::BOOLEAN,
        'options' => ['default' => true],
    ]);

    $metadata->mapField([
        'fieldName' => 'active',
        'type' => Types::BOOLEAN,
        'options' => ['default' => true],
    ]);

    $metadata->mapField([
        'fieldName' => 'source',
        'type' => Types::STRING,
        'length' => 32,
        'enumType' => ContactDataSource::class,
    ]);

    $metadata->mapManyToOne([
        'fieldName' => 'organization',
        'targetEntity' => OrganizationEntity::class,
        'inversedBy' => 'emailContacts',
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
        'inversedBy' => 'emailContacts',
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
