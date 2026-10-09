<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'commercial_invoice',
        'uniqueConstraints' => [
            'uniq_commercial_invoice_reference' => ['columns' => ['reference']],
            'uniq_commercial_invoice_reminder_action' => ['columns' => ['reminder_action_id']],
        ],
        'indexes' => ['idx_commercial_invoice_project' => ['columns' => ['project_id']]],
    ]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapField(['fieldName' => 'reference', 'type' => Types::STRING, 'length' => 64]);
    $metadata->mapField(['fieldName' => 'amountCents', 'columnName' => 'amount_cents', 'type' => Types::INTEGER]);
    $metadata->mapField(['fieldName' => 'issuedOn', 'columnName' => 'issued_on', 'type' => Types::DATE_IMMUTABLE]);
    $metadata->mapField(['fieldName' => 'paidOn', 'columnName' => 'paid_on', 'type' => Types::DATE_IMMUTABLE, 'nullable' => true]);
    $metadata->mapManyToOne([
        'fieldName' => 'project',
        'targetEntity' => CommercialProjectEntity::class,
        'inversedBy' => 'invoices',
        'joinColumns' => [['name' => 'project_id', 'referencedColumnName' => 'id', 'nullable' => false, 'onDelete' => 'RESTRICT']],
    ]);
    $metadata->mapManyToOne([
        'fieldName' => 'reminderAction',
        'targetEntity' => CommercialActionEntity::class,
        'joinColumns' => [['name' => 'reminder_action_id', 'referencedColumnName' => 'id', 'nullable' => true, 'onDelete' => 'RESTRICT']],
    ]);
};
