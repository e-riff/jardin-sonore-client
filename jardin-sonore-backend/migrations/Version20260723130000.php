<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260723130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize legacy database metadata with the current Doctrine mappings.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media_resource CHANGE uuid uuid BINARY(16) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE media_resource_theme RENAME INDEX idx_media_resource_theme_resource TO IDX_1DFB697A7E5AEFB6');
        $this->addSql('ALTER TABLE media_resource_theme RENAME INDEX idx_media_resource_theme_theme TO IDX_1DFB697A59027487');
        $this->addSql('ALTER TABLE instrument_tag CHANGE color color VARCHAR(7) NOT NULL');
        $this->addSql('ALTER TABLE mailing_audience_mask CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL, CHANGE uuid uuid BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE session_recommendation CHANGE uuid uuid BINARY(16) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE repertoire_item CHANGE uuid uuid BINARY(16) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE repertoire_item_theme RENAME INDEX idx_repertoire_item_theme_item TO IDX_A2D9AE16656D044F');
        $this->addSql('ALTER TABLE repertoire_item_theme RENAME INDEX idx_repertoire_item_theme_theme TO IDX_A2D9AE1659027487');
        $this->addSql('ALTER TABLE theme CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE session_summary CHANGE uuid uuid BINARY(16) NOT NULL, CHANGE session_date session_date DATE NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE mailing_campaign CHANGE applied_audience_mask_uuid applied_audience_mask_uuid BINARY(16) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('This migration removes legacy Doctrine metadata that cannot be restored safely.');
    }
}
