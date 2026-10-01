<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable(['name' => 'newsletter_subscription_request', 'uniqueConstraints' => [
        'uniq_newsletter_subscription_contact' => ['columns' => ['email_contact_id']],
        'uniq_newsletter_subscription_token' => ['columns' => ['token_hash']],
    ]]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapField(['fieldName' => 'tokenHash', 'columnName' => 'token_hash', 'type' => Types::STRING, 'length' => 64]);
    $metadata->mapField(['fieldName' => 'requestedAt', 'columnName' => 'requested_at', 'type' => Types::DATETIME_IMMUTABLE]);
    $metadata->mapField(['fieldName' => 'expiresAt', 'columnName' => 'expires_at', 'type' => Types::DATETIME_IMMUTABLE]);
    $metadata->mapField(['fieldName' => 'consumedAt', 'columnName' => 'consumed_at', 'type' => Types::DATETIME_IMMUTABLE, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'origin', 'type' => Types::STRING, 'length' => 64]);
    $metadata->mapManyToOne(['fieldName' => 'emailContact', 'targetEntity' => EmailContactEntity::class, 'joinColumns' => [[
        'name' => 'email_contact_id', 'referencedColumnName' => 'id', 'nullable' => false, 'onDelete' => 'CASCADE',
    ]]]);
};
