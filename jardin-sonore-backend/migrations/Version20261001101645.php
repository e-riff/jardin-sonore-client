<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261001101645 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store newsletter confirmation requests with one hashed token per email contact';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE newsletter_subscription_request (id INT AUTO_INCREMENT NOT NULL, token_hash VARCHAR(64) NOT NULL, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, consumed_at DATETIME DEFAULT NULL, origin VARCHAR(64) NOT NULL, email_contact_id INT NOT NULL, UNIQUE INDEX uniq_newsletter_subscription_contact (email_contact_id), UNIQUE INDEX uniq_newsletter_subscription_token (token_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE newsletter_subscription_request ADD CONSTRAINT FK_22467B0D4B12D795 FOREIGN KEY (email_contact_id) REFERENCES email_contact (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE newsletter_subscription_request DROP FOREIGN KEY FK_22467B0D4B12D795');
        $this->addSql('DROP TABLE newsletter_subscription_request');
    }
}
