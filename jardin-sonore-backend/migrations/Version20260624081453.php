<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260624081453 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create mailing campaign and recommendation tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mailing_campaign (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, internal_title VARCHAR(255) NOT NULL, email_subject VARCHAR(255) NOT NULL, public_title VARCHAR(255) NOT NULL, main_text LONGTEXT NOT NULL, template_key VARCHAR(64) NOT NULL, status VARCHAR(32) NOT NULL, audience_filter JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, last_test_sent_at DATETIME DEFAULT NULL, INDEX idx_mailing_campaign_status (status), INDEX idx_mailing_campaign_created_at (created_at), UNIQUE INDEX uniq_mailing_campaign_uuid (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mailing_recommendation (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, title VARCHAR(255) NOT NULL, text LONGTEXT NOT NULL, url VARCHAR(2048) DEFAULT NULL, link_label VARCHAR(255) DEFAULT NULL, image_path VARCHAR(255) DEFAULT NULL, position INT NOT NULL, active TINYINT DEFAULT 1 NOT NULL, campaign_id INT NOT NULL, INDEX idx_mailing_recommendation_campaign (campaign_id), INDEX idx_mailing_recommendation_position (position), UNIQUE INDEX uniq_mailing_recommendation_uuid (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE mailing_recommendation ADD CONSTRAINT FK_1915DD4CF639F774 FOREIGN KEY (campaign_id) REFERENCES mailing_campaign (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mailing_recommendation DROP FOREIGN KEY FK_1915DD4CF639F774');
        $this->addSql('DROP TABLE mailing_recommendation');
        $this->addSql('DROP TABLE mailing_campaign');
    }
}
