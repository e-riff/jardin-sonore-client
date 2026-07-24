<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260724110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record newsletter recommendation usage after successful campaign delivery.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE newsletter_recommendation_usage (id INT AUTO_INCREMENT NOT NULL, source_recommendation_uuid BINARY(16) NOT NULL, campaign_uuid BINARY(16) NOT NULL, campaign_title VARCHAR(255) NOT NULL, sent_at DATETIME NOT NULL, INDEX idx_newsletter_recommendation_usage_source_sent_at (source_recommendation_uuid, sent_at), UNIQUE INDEX uniq_newsletter_recommendation_usage_source_campaign (source_recommendation_uuid, campaign_uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE newsletter_recommendation_usage');
    }
}
