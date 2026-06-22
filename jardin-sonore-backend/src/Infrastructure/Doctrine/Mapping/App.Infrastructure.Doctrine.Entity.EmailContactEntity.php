<?php

declare(strict_types=1);

use App\Domain\Model\AddressBook\ContactDataSource;
use App\Domain\Model\AddressBook\EmailContactType;
use App\Infrastructure\Doctrine\Entity\ContactDetailsEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'email_contact',
        'indexes' => [
            'idx_email_contact_details' => ['columns' => ['contact_details_id']],
            'idx_email_contact_newsletter' => ['columns' => ['active', 'opt_in_newsletter', 'unsubscribed_at']],
        ],
        'uniqueConstraints' => [
            'uniq_email_contact_uuid' => ['columns' => ['uuid']],
            'uniq_email_contact_email_address' => ['columns' => ['email_address']],
            'uniq_email_contact_unsubscribe_token' => ['columns' => ['unsubscribe_token']],
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
        'fieldName' => 'type',
        'type' => Types::STRING,
        'length' => 32,
        'enumType' => EmailContactType::class,
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

    $metadata->mapField([
        'fieldName' => 'unsubscribeToken',
        'columnName' => 'unsubscribe_token',
        'type' => Types::STRING,
        'length' => 64,
        'unique' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'unsubscribedAt',
        'columnName' => 'unsubscribed_at',
        'type' => Types::DATETIME_IMMUTABLE,
        'nullable' => true,
    ]);

    $metadata->mapManyToOne([
        'fieldName' => 'contactDetails',
        'targetEntity' => ContactDetailsEntity::class,
        'inversedBy' => 'emailContacts',
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
