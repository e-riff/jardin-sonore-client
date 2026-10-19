<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019121753 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reserve one IMAP reply draft per source mail and track its append result.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mcp_mail_draft (id INT AUTO_INCREMENT NOT NULL, source_key VARCHAR(64) NOT NULL, draft_message_id VARCHAR(255) NOT NULL, folder VARCHAR(255) NOT NULL, imap_uid INT DEFAULT NULL, state VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_mcp_mail_draft_source (source_key), UNIQUE INDEX uniq_mcp_mail_draft_message (draft_message_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE mcp_mail_draft');
    }
}
