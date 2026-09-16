<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable(['name' => 'portal_session', 'indexes' => [
        'idx_portal_session_user_expiry' => ['columns' => ['user_id', 'expires_at']],
    ]]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapField(['fieldName' => 'tokenHash', 'columnName' => 'token_hash', 'type' => Types::STRING, 'length' => 64, 'unique' => true]);
    $metadata->mapField(['fieldName' => 'createdAt', 'columnName' => 'created_at', 'type' => Types::DATETIME_IMMUTABLE]);
    $metadata->mapField(['fieldName' => 'expiresAt', 'columnName' => 'expires_at', 'type' => Types::DATETIME_IMMUTABLE]);
    $metadata->mapField(['fieldName' => 'revokedAt', 'columnName' => 'revoked_at', 'type' => Types::DATETIME_IMMUTABLE, 'nullable' => true]);
    $metadata->mapManyToOne(['fieldName' => 'user', 'targetEntity' => UserEntity::class, 'joinColumns' => [['name' => 'user_id', 'referencedColumnName' => 'id', 'nullable' => false, 'onDelete' => 'CASCADE']]]);
    $metadata->mapManyToOne(['fieldName' => 'impersonatedBy', 'targetEntity' => AdminUserEntity::class, 'joinColumns' => [['name' => 'impersonated_by_id', 'referencedColumnName' => 'id', 'nullable' => true, 'onDelete' => 'SET NULL']]]);
};
