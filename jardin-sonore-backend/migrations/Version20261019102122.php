<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019102122 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique mail source keys to commercial events and actions for idempotent MCP follow-up.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commercial_action ADD mail_source_key VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_commercial_action_project_mail ON commercial_action (project_id, mail_source_key)');
        $this->addSql('ALTER TABLE commercial_event ADD mail_source_key VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_commercial_event_project_mail ON commercial_event (project_id, mail_source_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_commercial_action_project_mail ON commercial_action');
        $this->addSql('ALTER TABLE commercial_action DROP mail_source_key');
        $this->addSql('DROP INDEX uniq_commercial_event_project_mail ON commercial_event');
        $this->addSql('ALTER TABLE commercial_event DROP mail_source_key');
    }
}
