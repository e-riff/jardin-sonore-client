<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\CommercialQuoteEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'commercial_quote',
        'uniqueConstraints' => ['uniq_commercial_quote_reference' => ['columns' => ['reference']]],
        'indexes' => ['idx_commercial_quote_project' => ['columns' => ['project_id']]],
    ]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapField(['fieldName' => 'reference', 'type' => Types::STRING, 'length' => 64]);
    $metadata->mapField(['fieldName' => 'filename', 'type' => Types::STRING, 'length' => 255]);
    $metadata->mapField(['fieldName' => 'amountCents', 'columnName' => 'amount_cents', 'type' => Types::INTEGER]);
    $metadata->mapField(['fieldName' => 'sentOn', 'columnName' => 'sent_on', 'type' => Types::DATE_IMMUTABLE]);
    $metadata->mapField(['fieldName' => 'signedOn', 'columnName' => 'signed_on', 'type' => Types::DATE_IMMUTABLE, 'nullable' => true]);
    $metadata->mapManyToOne([
        'fieldName' => 'project',
        'targetEntity' => CommercialProjectEntity::class,
        'inversedBy' => 'quotes',
        'joinColumns' => [['name' => 'project_id', 'referencedColumnName' => 'id', 'nullable' => false, 'onDelete' => 'RESTRICT']],
    ]);
    $metadata->mapManyToOne([
        'fieldName' => 'replaces',
        'targetEntity' => CommercialQuoteEntity::class,
        'joinColumns' => [['name' => 'replaces_id', 'referencedColumnName' => 'id', 'nullable' => true, 'onDelete' => 'RESTRICT']],
    ]);
};
