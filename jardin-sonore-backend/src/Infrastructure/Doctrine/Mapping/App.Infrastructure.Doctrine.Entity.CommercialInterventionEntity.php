<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'commercial_intervention',
        'indexes' => [
            'idx_commercial_intervention_project_start' => ['columns' => ['project_id', 'starts_at']],
            'idx_commercial_intervention_status_end' => ['columns' => ['status', 'ends_at']],
        ],
        'uniqueConstraints' => [
            'uniq_commercial_intervention_google_event' => ['columns' => ['calendar_id', 'google_event_id']],
        ],
    ]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapField(['fieldName' => 'calendarId', 'columnName' => 'calendar_id', 'type' => Types::STRING, 'length' => 255]);
    $metadata->mapField(['fieldName' => 'googleEventId', 'columnName' => 'google_event_id', 'type' => Types::STRING, 'length' => 255]);
    $metadata->mapField(['fieldName' => 'title', 'type' => Types::STRING, 'length' => 255]);
    $metadata->mapField(['fieldName' => 'startsAt', 'columnName' => 'starts_at', 'type' => Types::DATETIME_IMMUTABLE]);
    $metadata->mapField(['fieldName' => 'endsAt', 'columnName' => 'ends_at', 'type' => Types::DATETIME_IMMUTABLE]);
    $metadata->mapField(['fieldName' => 'timeZone', 'columnName' => 'time_zone', 'type' => Types::STRING, 'length' => 64]);
    $metadata->mapField(['fieldName' => 'location', 'type' => Types::TEXT, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'etag', 'type' => Types::STRING, 'length' => 255]);
    $metadata->mapField(['fieldName' => 'syncedAt', 'columnName' => 'synced_at', 'type' => Types::DATETIME_IMMUTABLE]);
    $metadata->mapField(['fieldName' => 'recurringEventId', 'columnName' => 'recurring_event_id', 'type' => Types::STRING, 'length' => 255, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'originalStartsAt', 'columnName' => 'original_starts_at', 'type' => Types::DATETIME_IMMUTABLE, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'status', 'type' => Types::STRING, 'length' => 16]);
    $metadata->mapField(['fieldName' => 'canceledAt', 'columnName' => 'canceled_at', 'type' => Types::DATETIME_IMMUTABLE, 'nullable' => true]);
    $metadata->mapField(['fieldName' => 'syncError', 'columnName' => 'sync_error', 'type' => Types::TEXT, 'nullable' => true]);
    $metadata->mapManyToOne([
        'fieldName' => 'project',
        'targetEntity' => CommercialProjectEntity::class,
        'joinColumns' => [['name' => 'project_id', 'referencedColumnName' => 'id', 'nullable' => true, 'onDelete' => 'SET NULL']],
    ]);
};
