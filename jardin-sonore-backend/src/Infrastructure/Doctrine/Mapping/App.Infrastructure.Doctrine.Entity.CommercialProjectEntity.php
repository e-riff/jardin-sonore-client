<?php

declare(strict_types=1);

use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialEventEntity;
use App\Infrastructure\Doctrine\Entity\CommercialInvoiceEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectPersonEntity;
use App\Infrastructure\Doctrine\Entity\CommercialQuoteEntity;
use App\Infrastructure\Doctrine\Entity\CommercialRequestEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata;

return static function (ClassMetadata $metadata): void {
    $metadata->setPrimaryTable([
        'name' => 'commercial_project',
        'indexes' => [
            'idx_commercial_project_organization_status' => ['columns' => ['organization_id', 'status']],
            'idx_commercial_project_request' => ['columns' => ['request_id']],
            'idx_commercial_project_primary_contact' => ['columns' => ['primary_contact_id']],
        ],
    ]);
    $metadata->mapField(['fieldName' => 'id', 'type' => Types::INTEGER, 'id' => true]);
    $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_IDENTITY);
    $metadata->mapField(['fieldName' => 'title', 'type' => Types::STRING, 'length' => 255]);
    $metadata->mapField(['fieldName' => 'status', 'type' => Types::STRING, 'length' => 32]);
    $metadata->mapField(['fieldName' => 'createdAt', 'columnName' => 'created_at', 'type' => Types::DATETIME_IMMUTABLE]);
    $metadata->mapField(['fieldName' => 'closedAt', 'columnName' => 'closed_at', 'type' => Types::DATETIME_IMMUTABLE, 'nullable' => true]);
    $metadata->mapManyToOne([
        'fieldName' => 'organization',
        'targetEntity' => OrganizationEntity::class,
        'joinColumns' => [['name' => 'organization_id', 'referencedColumnName' => 'id', 'nullable' => false, 'onDelete' => 'RESTRICT']],
    ]);
    $metadata->mapManyToOne([
        'fieldName' => 'request',
        'targetEntity' => CommercialRequestEntity::class,
        'inversedBy' => 'projects',
        'joinColumns' => [['name' => 'request_id', 'referencedColumnName' => 'id', 'nullable' => true, 'onDelete' => 'RESTRICT']],
    ]);
    $metadata->mapManyToOne([
        'fieldName' => 'primaryContact',
        'targetEntity' => PersonEntity::class,
        'joinColumns' => [['name' => 'primary_contact_id', 'referencedColumnName' => 'id', 'nullable' => true, 'onDelete' => 'RESTRICT']],
    ]);
    $metadata->mapOneToMany(['fieldName' => 'people', 'targetEntity' => CommercialProjectPersonEntity::class, 'mappedBy' => 'project', 'cascade' => ['persist']]);
    $metadata->mapOneToMany(['fieldName' => 'actions', 'targetEntity' => CommercialActionEntity::class, 'mappedBy' => 'project', 'cascade' => ['persist']]);
    $metadata->mapOneToMany(['fieldName' => 'events', 'targetEntity' => CommercialEventEntity::class, 'mappedBy' => 'project', 'cascade' => ['persist'], 'orderBy' => ['occurredAt' => 'ASC', 'id' => 'ASC']]);
    $metadata->mapOneToMany(['fieldName' => 'quotes', 'targetEntity' => CommercialQuoteEntity::class, 'mappedBy' => 'project', 'cascade' => ['persist'], 'orderBy' => ['sentOn' => 'ASC', 'id' => 'ASC']]);
    $metadata->mapOneToMany(['fieldName' => 'invoices', 'targetEntity' => CommercialInvoiceEntity::class, 'mappedBy' => 'project', 'cascade' => ['persist'], 'orderBy' => ['issuedOn' => 'ASC', 'id' => 'ASC']]);
};
