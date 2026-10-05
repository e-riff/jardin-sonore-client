<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261005170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track the one-time claim for a mailing campaign completion summary email.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mailing_campaign ADD summary_notification_claimed_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mailing_campaign DROP summary_notification_claimed_at');
    }
}
