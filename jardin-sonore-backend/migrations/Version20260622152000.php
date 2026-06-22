<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260622152000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email contact unsubscribe token and unsubscribe date for newsletter targeting.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_email_contact_newsletter ON email_contact');
        $this->addSql('ALTER TABLE email_contact ADD unsubscribe_token VARCHAR(64) DEFAULT NULL, ADD unsubscribed_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE email_contact SET unsubscribe_token = REPLACE(UUID(), \'-\', \'\') WHERE unsubscribe_token IS NULL');
        $this->addSql('ALTER TABLE email_contact CHANGE unsubscribe_token unsubscribe_token VARCHAR(64) NOT NULL');
        $this->addSql('CREATE INDEX idx_email_contact_newsletter ON email_contact (active, opt_in_newsletter, unsubscribed_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_email_contact_unsubscribe_token ON email_contact (unsubscribe_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_email_contact_newsletter ON email_contact');
        $this->addSql('DROP INDEX uniq_email_contact_unsubscribe_token ON email_contact');
        $this->addSql('ALTER TABLE email_contact DROP unsubscribe_token, DROP unsubscribed_at');
        $this->addSql('CREATE INDEX idx_email_contact_newsletter ON email_contact (active, opt_in_newsletter)');
    }
}
