<?php

declare(strict_types=1);

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable(['name' => 'google_calendar_connection']);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->mapField(['fieldName' => 'encryptedRefreshToken', 'columnName' => 'encrypted_refresh_token', 'type' => Types::TEXT]);
    $metadata->mapField(['fieldName' => 'accountEmail', 'columnName' => 'account_email', 'type' => Types::STRING, 'length' => 255]);
    $metadata->mapField(['fieldName' => 'connectedAt', 'columnName' => 'connected_at', 'type' => Types::DATETIME_IMMUTABLE]);
    $metadata->mapField(['fieldName' => 'lastSuccessAt', 'columnName' => 'last_success_at', 'type' => Types::DATETIME_IMMUTABLE, 'nullable' => true]);
};
