<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126195323 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist per-campaign outreach exclusion window and recipient cancellation reason';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mailing_campaign ADD outreach_exclusion_weeks INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE mailing_delivery_recipient ADD cancellation_reason VARCHAR(32) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mailing_campaign DROP outreach_exclusion_weeks');
        $this->addSql('ALTER TABLE mailing_delivery_recipient DROP cancellation_reason');
    }
}
