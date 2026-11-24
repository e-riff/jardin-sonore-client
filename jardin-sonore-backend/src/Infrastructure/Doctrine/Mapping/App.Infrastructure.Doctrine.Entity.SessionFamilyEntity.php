<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\ThemeEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable(['name' => 'session_family', 'uniqueConstraints' => ['uniq_session_family_uuid' => ['columns' => ['uuid']]]]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapField(['fieldName' => 'uuid', 'type' => UuidType::NAME, 'unique' => true]);
    $metadata->mapField(['fieldName' => 'name', 'type' => Types::STRING, 'length' => 255]);
    $metadata->mapManyToMany(['fieldName' => 'themes', 'targetEntity' => ThemeEntity::class, 'joinTable' => ['name' => 'session_family_theme', 'joinColumns' => [['name' => 'family_id', 'referencedColumnName' => 'id', 'onDelete' => 'CASCADE']], 'inverseJoinColumns' => [['name' => 'theme_id', 'referencedColumnName' => 'id', 'onDelete' => 'CASCADE']]]]);
};
