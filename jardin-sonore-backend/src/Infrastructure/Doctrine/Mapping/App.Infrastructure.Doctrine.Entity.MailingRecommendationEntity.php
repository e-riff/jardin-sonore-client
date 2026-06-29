<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\MailingCampaignEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Bridge\Doctrine\Types\UuidType;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'mailing_recommendation',
        'indexes' => [
            'idx_mailing_recommendation_campaign' => ['columns' => ['campaign_id']],
            'idx_mailing_recommendation_position' => ['columns' => ['position']],
            'idx_mailing_recommendation_source' => ['columns' => ['source_recommendation_uuid']],
        ],
        'uniqueConstraints' => [
            'uniq_mailing_recommendation_uuid' => ['columns' => ['uuid']],
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
        'fieldName' => 'title',
        'type' => Types::STRING,
        'length' => 255,
    ]);

    $metadata->mapField([
        'fieldName' => 'text',
        'type' => Types::TEXT,
    ]);

    $metadata->mapField([
        'fieldName' => 'url',
        'type' => Types::STRING,
        'length' => 2048,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'linkLabel',
        'columnName' => 'link_label',
        'type' => Types::STRING,
        'length' => 255,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'imagePath',
        'columnName' => 'image_path',
        'type' => Types::STRING,
        'length' => 255,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'position',
        'type' => Types::INTEGER,
    ]);

    $metadata->mapField([
        'fieldName' => 'sourceRecommendationUuid',
        'columnName' => 'source_recommendation_uuid',
        'type' => UuidType::NAME,
        'nullable' => true,
    ]);

    $metadata->mapField([
        'fieldName' => 'active',
        'type' => Types::BOOLEAN,
        'options' => ['default' => true],
    ]);

    $metadata->mapManyToOne([
        'fieldName' => 'campaign',
        'targetEntity' => MailingCampaignEntity::class,
        'inversedBy' => 'recommendations',
        'joinColumns' => [
            [
                'name' => 'campaign_id',
                'referencedColumnName' => 'id',
                'nullable' => false,
                'onDelete' => 'CASCADE',
            ],
        ],
    ]);
};
