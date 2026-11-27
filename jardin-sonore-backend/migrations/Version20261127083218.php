<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127083218 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Separate directory mailing admission and newsletter/outreach refusals while preserving historical exclusions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_contact ADD directory_mailing_allowed TINYINT DEFAULT 0 NOT NULL, ADD newsletter_refused TINYINT DEFAULT 0 NOT NULL, ADD newsletter_refused_at DATETIME DEFAULT NULL, ADD outreach_refused TINYINT DEFAULT 0 NOT NULL, ADD outreach_refused_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE email_contact SET directory_mailing_allowed = (opt_in_newsletter = 1 AND unsubscribed_at IS NULL), newsletter_refused = (opt_in_newsletter = 0 OR unsubscribed_at IS NOT NULL), outreach_refused = (opt_in_newsletter = 0 OR unsubscribed_at IS NOT NULL), newsletter_refused_at = unsubscribed_at, outreach_refused_at = unsubscribed_at');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_contact DROP directory_mailing_allowed, DROP newsletter_refused, DROP newsletter_refused_at, DROP outreach_refused, DROP outreach_refused_at');
    }
}
