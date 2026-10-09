<?php

declare(strict_types=1);

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable(['name' => 'commercial_digest_settings']);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
    $metadata->mapField(['fieldName' => 'enabled', 'type' => Types::BOOLEAN]);
    $metadata->mapField(['fieldName' => 'sendTime', 'columnName' => 'send_time', 'type' => Types::STRING, 'length' => 5]);
};
