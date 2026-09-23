<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260922130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the date at which a session is shared with an organization.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE session_summary_organization DROP PRIMARY KEY, ADD id INT AUTO_INCREMENT NOT NULL FIRST, ADD PRIMARY KEY (id), ADD UNIQUE INDEX uniq_session_summary_organization (session_summary_id, organization_id)');
        $this->addSql('ALTER TABLE session_summary_organization ADD shared_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE session_summary_organization SET shared_at = CURRENT_TIMESTAMP');
        $this->addSql('ALTER TABLE session_summary_organization MODIFY shared_at DATETIME NOT NULL');
        $this->addSql('CREATE INDEX idx_session_summary_organization_shared_at ON session_summary_organization (shared_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_session_summary_organization_shared_at ON session_summary_organization');
        $this->addSql('ALTER TABLE session_summary_organization DROP INDEX uniq_session_summary_organization, DROP PRIMARY KEY, DROP id, DROP shared_at, ADD PRIMARY KEY (session_summary_id, organization_id)');
    }
}
