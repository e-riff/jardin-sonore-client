<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use App\Infrastructure\Doctrine\Entity\UserEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'session_notification_delivery',
        'indexes' => [
            'idx_session_notification_status_queued' => ['columns' => ['status', 'queued_at']],
        ],
        'uniqueConstraints' => [
            'uniq_session_notification_session_user' => ['columns' => ['session_summary_id', 'user_id']],
        ],
    ]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapField(['fieldName' => 'status', 'type' => Types::STRING, 'length' => 16]);
    $metadata->mapField(['fieldName' => 'createdAt', 'columnName' => 'created_at', 'type' => Types::DATETIME_IMMUTABLE]);
    $metadata->mapField(['fieldName' => 'queuedAt', 'columnName' => 'queued_at', 'type' => Types::DATETIME_IMMUTABLE, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'sentAt', 'columnName' => 'sent_at', 'type' => Types::DATETIME_IMMUTABLE, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'attempts', 'type' => Types::INTEGER, 'options' => ['default' => 0]]);
    $metadata->mapField(['fieldName' => 'lastError', 'columnName' => 'last_error', 'type' => Types::TEXT, 'nullable' => true]);
    $metadata->mapManyToOne([
        'fieldName' => 'sessionSummary',
        'targetEntity' => SessionSummaryEntity::class,
        'joinColumns' => [['name' => 'session_summary_id', 'referencedColumnName' => 'id', 'nullable' => false, 'onDelete' => 'CASCADE']],
    ]);
    $metadata->mapManyToOne([
        'fieldName' => 'user',
        'targetEntity' => UserEntity::class,
        'joinColumns' => [['name' => 'user_id', 'referencedColumnName' => 'id', 'nullable' => false, 'onDelete' => 'CASCADE']],
    ]);
};
