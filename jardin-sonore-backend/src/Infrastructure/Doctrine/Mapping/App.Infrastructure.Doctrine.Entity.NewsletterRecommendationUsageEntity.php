<?php

declare(strict_types=1);

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'newsletter_recommendation_usage',
        'indexes' => [
            'idx_newsletter_recommendation_usage_source_sent_at' => ['columns' => ['source_recommendation_uuid', 'sent_at']],
        ],
        'uniqueConstraints' => [
            'uniq_newsletter_recommendation_usage_source_campaign' => ['columns' => ['source_recommendation_uuid', 'campaign_uuid']],
        ],
    ]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapField(['fieldName' => 'sourceRecommendationUuid', 'columnName' => 'source_recommendation_uuid', 'type' => UuidType::NAME]);
    $metadata->mapField(['fieldName' => 'campaignUuid', 'columnName' => 'campaign_uuid', 'type' => UuidType::NAME]);
    $metadata->mapField(['fieldName' => 'campaignTitle', 'columnName' => 'campaign_title', 'type' => Types::STRING, 'length' => 255]);
    $metadata->mapField(['fieldName' => 'sentAt', 'columnName' => 'sent_at', 'type' => Types::DATETIME_IMMUTABLE]);
};
