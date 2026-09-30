<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930130613 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store explicit free newsletter subscription membership and confirmation history without changing existing consent.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_contact ADD free_newsletter_subscription TINYINT DEFAULT 0 NOT NULL, ADD free_newsletter_subscription_confirmed_at DATETIME DEFAULT NULL, ADD free_newsletter_subscription_origin VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_contact DROP free_newsletter_subscription, DROP free_newsletter_subscription_confirmed_at, DROP free_newsletter_subscription_origin');
    }
}
