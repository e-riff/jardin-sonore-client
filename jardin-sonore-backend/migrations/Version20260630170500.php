<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260630170500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create mailing delivery recipient queue table for wave-based campaign dispatch.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mailing_delivery_recipient (id BIGINT AUTO_INCREMENT NOT NULL, campaign_uuid CHAR(36) NOT NULL, email_address VARCHAR(255) NOT NULL, unsubscribe_token VARCHAR(64) NOT NULL, display_name VARCHAR(255) DEFAULT NULL, status VARCHAR(32) NOT NULL, queued_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', dispatched_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', failed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_error LONGTEXT DEFAULT NULL, INDEX idx_mailing_delivery_campaign_status (campaign_uuid, status), INDEX idx_mailing_delivery_dispatched_at (dispatched_at), UNIQUE INDEX uniq_mailing_delivery_campaign_email (campaign_uuid, email_address), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE mailing_delivery_recipient');
    }
}
