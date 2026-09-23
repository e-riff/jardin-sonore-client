<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260923100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store portal user preference for new session email notifications.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE portal_user ADD new_session_notifications_enabled TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE portal_user DROP new_session_notifications_enabled');
    }
}
