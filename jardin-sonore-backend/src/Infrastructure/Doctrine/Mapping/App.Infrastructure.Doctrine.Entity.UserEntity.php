<?php

declare(strict_types=1);

use App\Domain\Model\Portal\UserStatus;
use App\Infrastructure\Doctrine\Entity\UserOrganizationAccessEntity;
use App\Infrastructure\Doctrine\Entity\UserPasswordTokenEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable(['name' => 'portal_user', 'uniqueConstraints' => ['uniq_portal_user_uuid' => ['columns' => ['uuid']], 'uniq_portal_user_email' => ['columns' => ['email']]]]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapField(['fieldName' => 'uuid', 'type' => UuidType::NAME, 'unique' => true]);
    $metadata->mapField(['fieldName' => 'email', 'type' => Types::STRING, 'length' => 180, 'unique' => true]);
    $metadata->mapField(['fieldName' => 'password', 'type' => Types::STRING, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'status', 'type' => Types::STRING, 'length' => 16, 'enumType' => UserStatus::class]);
    $metadata->mapField(['fieldName' => 'active', 'type' => Types::BOOLEAN, 'options' => ['default' => true]]);
    $metadata->mapOneToMany(['fieldName' => 'organizationAccesses', 'targetEntity' => UserOrganizationAccessEntity::class, 'mappedBy' => 'user', 'cascade' => ['persist'], 'orphanRemoval' => true]);
    $metadata->mapOneToMany(['fieldName' => 'passwordTokens', 'targetEntity' => UserPasswordTokenEntity::class, 'mappedBy' => 'user', 'cascade' => ['persist'], 'orphanRemoval' => true]);
};
