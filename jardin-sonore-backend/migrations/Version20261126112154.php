<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126112154 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Distinguish mailing campaign types and retain targeted organizations per recipient';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mailing_delivery_recipient_organization (id BIGINT AUTO_INCREMENT NOT NULL, organization_uuid BINARY(16) NOT NULL, organization_name VARCHAR(255) NOT NULL, organization_type VARCHAR(32) DEFAULT NULL, municipality_insee_code VARCHAR(5) DEFAULT NULL, delivery_recipient_id BIGINT NOT NULL, INDEX IDX_45306C6F76AC767C (delivery_recipient_id), INDEX idx_mailing_recipient_organization_uuid (organization_uuid), UNIQUE INDEX uniq_mailing_recipient_organization (delivery_recipient_id, organization_uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE mailing_delivery_recipient_organization ADD CONSTRAINT FK_45306C6F76AC767C FOREIGN KEY (delivery_recipient_id) REFERENCES mailing_delivery_recipient (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mailing_campaign ADD structured_content JSON DEFAULT NULL, ADD campaign_type VARCHAR(16) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mailing_delivery_recipient_organization DROP FOREIGN KEY FK_45306C6F76AC767C');
        $this->addSql('DROP TABLE mailing_delivery_recipient_organization');
        $this->addSql('ALTER TABLE mailing_campaign DROP structured_content, DROP campaign_type');
    }
}
