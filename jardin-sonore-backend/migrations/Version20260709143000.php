<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260709143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create reusable mailing audience masks and keep applied mask metadata on campaigns.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mailing_audience_mask (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(255) NOT NULL, audience_filter JSON NOT NULL, materialized_municipality_insee_codes JSON NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_mailing_audience_mask_uuid (uuid), INDEX idx_mailing_audience_mask_name (name), INDEX idx_mailing_audience_mask_updated_at (updated_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE mailing_campaign ADD applied_audience_mask_uuid BINARY(16) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD applied_audience_mask_name VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mailing_campaign DROP applied_audience_mask_uuid, DROP applied_audience_mask_name');
        $this->addSql('DROP TABLE mailing_audience_mask');
    }
}
