<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260629101816 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create newsletter recommendation catalog and link campaign copies to their source.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE newsletter_recommendation (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, title VARCHAR(255) NOT NULL, text LONGTEXT NOT NULL, url VARCHAR(2048) DEFAULT NULL, link_label VARCHAR(255) DEFAULT NULL, image_path VARCHAR(255) DEFAULT NULL, active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_newsletter_recommendation_title (title), INDEX idx_newsletter_recommendation_active (active), UNIQUE INDEX uniq_newsletter_recommendation_uuid (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE mailing_recommendation ADD source_recommendation_uuid BINARY(16) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_mailing_recommendation_source ON mailing_recommendation (source_recommendation_uuid)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE newsletter_recommendation');
        $this->addSql('DROP INDEX idx_mailing_recommendation_source ON mailing_recommendation');
        $this->addSql('ALTER TABLE mailing_recommendation DROP source_recommendation_uuid');
    }
}
