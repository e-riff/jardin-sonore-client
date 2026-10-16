<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'commercial_invoice_preparation',
        'uniqueConstraints' => [
            'uniq_commercial_invoice_preparation_project_month' => ['columns' => ['project_id', 'service_month']],
            'uniq_commercial_invoice_preparation_action' => ['columns' => ['action_id']],
        ],
    ]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapField(['fieldName' => 'serviceMonth', 'columnName' => 'service_month', 'type' => Types::DATE_IMMUTABLE]);
    $metadata->mapManyToOne([
        'fieldName' => 'project',
        'targetEntity' => CommercialProjectEntity::class,
        'joinColumns' => [['name' => 'project_id', 'referencedColumnName' => 'id', 'nullable' => false, 'onDelete' => 'CASCADE']],
    ]);
    $metadata->mapOneToOne([
        'fieldName' => 'action',
        'targetEntity' => CommercialActionEntity::class,
        'joinColumns' => [['name' => 'action_id', 'referencedColumnName' => 'id', 'nullable' => false, 'onDelete' => 'CASCADE']],
    ]);
};
