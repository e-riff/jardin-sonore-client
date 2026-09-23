<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'session_summary_organization',
        'indexes' => [
            'idx_session_summary_organization_shared_at' => ['columns' => ['shared_at']],
        ],
        'uniqueConstraints' => [
            'uniq_session_summary_organization' => ['columns' => ['session_summary_id', 'organization_id']],
        ],
    ]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapField(['fieldName' => 'sharedAt', 'columnName' => 'shared_at', 'type' => Types::DATETIME_IMMUTABLE]);
    $metadata->mapManyToOne(['fieldName' => 'sessionSummary', 'targetEntity' => SessionSummaryEntity::class, 'inversedBy' => 'organizationShares', 'joinColumns' => [['name' => 'session_summary_id', 'referencedColumnName' => 'id', 'nullable' => false, 'onDelete' => 'CASCADE']]]);
    $metadata->mapManyToOne(['fieldName' => 'organization', 'targetEntity' => OrganizationEntity::class, 'joinColumns' => [['name' => 'organization_id', 'referencedColumnName' => 'id', 'nullable' => false, 'onDelete' => 'CASCADE']]]);
};
