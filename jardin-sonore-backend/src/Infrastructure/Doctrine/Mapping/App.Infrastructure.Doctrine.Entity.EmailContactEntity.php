<?php

declare(strict_types=1);

use App\Domain\Model\AddressBook\ContactDataSource;
use App\Infrastructure\Doctrine\Entity\EmailContactLinkEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'email_contact',
        'indexes' => [
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
        'fieldName' => 'emailAddress',
        'columnName' => 'email_address',
        'type' => Types::STRING,
        'length' => 255,
        'unique' => true,
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
        'nullable' => true,
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

    $metadata->mapOneToMany([
        'fieldName' => 'emailContactLinks',
        'targetEntity' => EmailContactLinkEntity::class,
        'mappedBy' => 'emailContact',
    ]);
};
