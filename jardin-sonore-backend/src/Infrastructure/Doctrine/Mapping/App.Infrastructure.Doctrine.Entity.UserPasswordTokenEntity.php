<?php

declare(strict_types=1);

use App\Domain\Model\Portal\PasswordTokenType;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable(['name' => 'portal_user_password_token', 'indexes' => [
        'idx_portal_user_password_token_hash' => ['columns' => ['token_hash']],
        'idx_portal_user_password_token_user_type' => ['columns' => ['user_id', 'type']],
    ]]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapField(['fieldName' => 'type', 'type' => Types::STRING, 'length' => 16, 'enumType' => PasswordTokenType::class]);
    $metadata->mapField(['fieldName' => 'tokenHash', 'columnName' => 'token_hash', 'type' => Types::STRING, 'length' => 64]);
    $metadata->mapField(['fieldName' => 'expiresAt', 'columnName' => 'expires_at', 'type' => Types::DATETIME_IMMUTABLE]);
    $metadata->mapField(['fieldName' => 'consumedAt', 'columnName' => 'consumed_at', 'type' => Types::DATETIME_IMMUTABLE, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'invalidatedAt', 'columnName' => 'invalidated_at', 'type' => Types::DATETIME_IMMUTABLE, 'nullable' => true]);
    $metadata->mapManyToOne(['fieldName' => 'user', 'targetEntity' => UserEntity::class, 'inversedBy' => 'passwordTokens', 'joinColumns' => [['name' => 'user_id', 'referencedColumnName' => 'id', 'nullable' => false, 'onDelete' => 'CASCADE']]]);
};
