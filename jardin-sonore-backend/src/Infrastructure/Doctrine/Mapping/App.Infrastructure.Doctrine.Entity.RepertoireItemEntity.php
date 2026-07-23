<?php

declare(strict_types=1);

use App\Domain\Model\Session\RepertoireItemType;
use App\Infrastructure\Doctrine\Entity\ThemeEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'repertoire_item',
        'indexes' => [
            'idx_repertoire_item_type' => ['columns' => ['type']],
            'idx_repertoire_item_active' => ['columns' => ['active']],
        ],
        'uniqueConstraints' => [
            'uniq_repertoire_item_uuid' => ['columns' => ['uuid']],
        ],
    ]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapField(['fieldName' => 'uuid', 'type' => UuidType::NAME, 'unique' => true]);
    $metadata->mapField(['fieldName' => 'type', 'type' => Types::STRING, 'length' => 32, 'enumType' => RepertoireItemType::class]);
    $metadata->mapField(['fieldName' => 'title', 'type' => Types::STRING, 'length' => 255]);
    $metadata->mapField(['fieldName' => 'source', 'type' => Types::STRING, 'length' => 255, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'body', 'type' => Types::TEXT]);
    $metadata->mapField(['fieldName' => 'contentBlocks', 'columnName' => 'content_blocks', 'type' => Types::JSON]);
    $metadata->mapField(['fieldName' => 'notes', 'type' => Types::TEXT, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'linkedMediaUuids', 'columnName' => 'linked_media_uuids', 'type' => Types::JSON]);
    $metadata->mapField(['fieldName' => 'active', 'type' => Types::BOOLEAN, 'options' => ['default' => true]]);
    $metadata->mapField(['fieldName' => 'createdAt', 'columnName' => 'created_at', 'type' => Types::DATETIME_IMMUTABLE]);
    $metadata->mapField(['fieldName' => 'updatedAt', 'columnName' => 'updated_at', 'type' => Types::DATETIME_IMMUTABLE]);
    $metadata->mapManyToMany(['fieldName' => 'themes', 'targetEntity' => ThemeEntity::class, 'joinTable' => ['name' => 'repertoire_item_theme', 'joinColumns' => [['name' => 'repertoire_item_id', 'referencedColumnName' => 'id', 'onDelete' => 'CASCADE']], 'inverseJoinColumns' => [['name' => 'theme_id', 'referencedColumnName' => 'id', 'onDelete' => 'CASCADE']]]]);
};
