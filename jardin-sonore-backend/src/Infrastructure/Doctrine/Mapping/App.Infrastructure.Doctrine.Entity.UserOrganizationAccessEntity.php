<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable(['name' => 'user_organization_access', 'uniqueConstraints' => ['uniq_user_organization_access' => ['columns' => ['user_id', 'organization_id']]]]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapField(['fieldName' => 'active', 'type' => Types::BOOLEAN, 'options' => ['default' => true]]);
    $metadata->mapManyToOne(['fieldName' => 'user', 'targetEntity' => UserEntity::class, 'inversedBy' => 'organizationAccesses', 'joinColumns' => [['name' => 'user_id', 'referencedColumnName' => 'id', 'nullable' => false, 'onDelete' => 'CASCADE']]]);
    $metadata->mapManyToOne(['fieldName' => 'organization', 'targetEntity' => OrganizationEntity::class, 'joinColumns' => [['name' => 'organization_id', 'referencedColumnName' => 'id', 'nullable' => false, 'onDelete' => 'CASCADE']]]);
    $metadata->mapManyToOne(['fieldName' => 'person', 'targetEntity' => PersonEntity::class, 'joinColumns' => [['name' => 'person_id', 'referencedColumnName' => 'id', 'nullable' => true, 'onDelete' => 'SET NULL']]]);
};
