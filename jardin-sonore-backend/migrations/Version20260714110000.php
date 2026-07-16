<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260714110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create session summary and session resource tables for printable session reports.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE repertoire_item (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', type VARCHAR(32) NOT NULL, title VARCHAR(255) NOT NULL, source VARCHAR(255) DEFAULT NULL, body LONGTEXT NOT NULL, content_blocks JSON NOT NULL, notes LONGTEXT DEFAULT NULL, linked_media_uuids JSON NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uniq_repertoire_item_uuid (uuid), INDEX idx_repertoire_item_type (type), INDEX idx_repertoire_item_active (active), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE media_resource (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', type VARCHAR(32) NOT NULL, title VARCHAR(255) NOT NULL, source VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, primary_url VARCHAR(2048) NOT NULL, secondary_url VARCHAR(2048) DEFAULT NULL, image_url VARCHAR(2048) DEFAULT NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uniq_media_resource_uuid (uuid), INDEX idx_media_resource_type (type), INDEX idx_media_resource_active (active), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE session_recommendation (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', title VARCHAR(255) NOT NULL, text LONGTEXT NOT NULL, notes LONGTEXT DEFAULT NULL, primary_url VARCHAR(2048) DEFAULT NULL, secondary_url VARCHAR(2048) DEFAULT NULL, image_url VARCHAR(2048) DEFAULT NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uniq_session_recommendation_uuid (uuid), INDEX idx_session_recommendation_active (active), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE session_summary (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)', title VARCHAR(255) NOT NULL, session_date DATE NOT NULL COMMENT '(DC2Type:date_immutable)', organization_name VARCHAR(255) NOT NULL, theme VARCHAR(255) DEFAULT NULL, general_notes LONGTEXT DEFAULT NULL, material_summary LONGTEXT DEFAULT NULL, further_exploration LONGTEXT DEFAULT NULL, instrument_uuids JSON NOT NULL, sequences JSON NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uniq_session_summary_uuid (uuid), INDEX idx_session_summary_date (session_date), INDEX idx_session_summary_organization (organization_name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE session_summary');
        $this->addSql('DROP TABLE session_recommendation');
        $this->addSql('DROP TABLE media_resource');
        $this->addSql('DROP TABLE repertoire_item');
    }
}
