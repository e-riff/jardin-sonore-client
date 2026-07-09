<?php

declare(strict_types=1);

use App\Domain\Model\Mailing\MailingCampaignStatus;
use App\Infrastructure\Doctrine\Entity\MailingRecommendationEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'mailing_campaign',
        'indexes' => [
            'idx_mailing_campaign_status' => ['columns' => ['status']],
            'idx_mailing_campaign_created_at' => ['columns' => ['created_at']],
        ],
        'uniqueConstraints' => [
            'uniq_mailing_campaign_uuid' => ['columns' => ['uuid']],
        ],
    ]);

    $metadata->mapField([
        'fieldName' => 'id',
        'type' => Types::INTEGER,
        'id' => true,
    ]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);

    $metadata->mapField([
        'fieldName' => 'uuid',
        'type' => UuidType::NAME,
        'unique' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'internalTitle',
        'columnName' => 'internal_title',
        'type' => Types::STRING,
        'length' => 255,
    ]);

    $metadata->mapField([
        'fieldName' => 'emailSubject',
        'columnName' => 'email_subject',
        'type' => Types::STRING,
        'length' => 255,
    ]);

    $metadata->mapField([
        'fieldName' => 'publicTitle',
        'columnName' => 'public_title',
        'type' => Types::STRING,
        'length' => 255,
    ]);

    $metadata->mapField([
        'fieldName' => 'mainText',
        'columnName' => 'main_text',
        'type' => Types::TEXT,
    ]);

    $metadata->mapField([
        'fieldName' => 'subtitle',
        'columnName' => 'subtitle',
        'type' => Types::STRING,
        'length' => 255,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'callToActionLabel',
        'columnName' => 'cta_label',
        'type' => Types::STRING,
        'length' => 255,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'callToActionUrl',
        'columnName' => 'cta_url',
        'type' => Types::STRING,
        'length' => 2048,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'bannerImagePath',
        'columnName' => 'banner_image_path',
        'type' => Types::STRING,
        'length' => 255,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'templateKey',
        'columnName' => 'template_key',
        'type' => Types::STRING,
        'length' => 64,
    ]);

    $metadata->mapField([
        'fieldName' => 'status',
        'type' => Types::STRING,
        'length' => 32,
        'enumType' => MailingCampaignStatus::class,
    ]);

    $metadata->mapField([
        'fieldName' => 'audienceFilter',
        'columnName' => 'audience_filter',
        'type' => Types::JSON,
    ]);

    $metadata->mapField([
        'fieldName' => 'appliedAudienceMaskUuid',
        'columnName' => 'applied_audience_mask_uuid',
        'type' => UuidType::NAME,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'appliedAudienceMaskName',
        'columnName' => 'applied_audience_mask_name',
        'type' => Types::STRING,
        'length' => 255,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'createdAt',
        'columnName' => 'created_at',
        'type' => Types::DATETIME_IMMUTABLE,
    ]);

    $metadata->mapField([
        'fieldName' => 'updatedAt',
        'columnName' => 'updated_at',
        'type' => Types::DATETIME_IMMUTABLE,
    ]);

    $metadata->mapField([
        'fieldName' => 'lastTestSentAt',
        'columnName' => 'last_test_sent_at',
        'type' => Types::DATETIME_IMMUTABLE,
        'nullable' => true,
    ]);

    $metadata->mapOneToMany([
        'fieldName' => 'recommendations',
        'targetEntity' => MailingRecommendationEntity::class,
        'mappedBy' => 'campaign',
        'cascade' => ['persist', 'remove'],
        'orphanRemoval' => true,
        'orderBy' => ['position' => 'ASC'],
    ]);
};
