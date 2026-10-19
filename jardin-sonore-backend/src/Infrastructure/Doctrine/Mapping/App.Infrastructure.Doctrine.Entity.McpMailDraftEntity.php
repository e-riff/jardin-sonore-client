<?php

declare(strict_types=1);

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'mcp_mail_draft',
        'uniqueConstraints' => [
            'uniq_mcp_mail_draft_source' => ['columns' => ['source_key']],
            'uniq_mcp_mail_draft_message' => ['columns' => ['draft_message_id']],
        ],
    ]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapField(['fieldName' => 'sourceKey', 'columnName' => 'source_key', 'type' => Types::STRING, 'length' => 64]);
    $metadata->mapField(['fieldName' => 'draftMessageId', 'columnName' => 'draft_message_id', 'type' => Types::STRING, 'length' => 255]);
    $metadata->mapField(['fieldName' => 'folder', 'type' => Types::STRING, 'length' => 255]);
    $metadata->mapField(['fieldName' => 'imapUid', 'columnName' => 'imap_uid', 'type' => Types::INTEGER, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'pendingMessageId', 'columnName' => 'pending_message_id', 'type' => Types::STRING, 'length' => 255, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'state', 'type' => Types::STRING, 'length' => 16]);
    $metadata->mapField(['fieldName' => 'createdAt', 'columnName' => 'created_at', 'type' => Types::DATETIME_IMMUTABLE]);
    $metadata->mapField(['fieldName' => 'updatedAt', 'columnName' => 'updated_at', 'type' => Types::DATETIME_IMMUTABLE]);
};
