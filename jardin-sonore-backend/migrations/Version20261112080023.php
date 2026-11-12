<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261112080023 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Trace and deduplicate personal notifications requested through MCP.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mcp_personal_notification (id INT AUTO_INCREMENT NOT NULL, request_id VARCHAR(128) NOT NULL, fingerprint VARCHAR(64) NOT NULL, state VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, finished_at DATETIME DEFAULT NULL, INDEX idx_mcp_notification_created (created_at), UNIQUE INDEX uniq_mcp_notification_request (request_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE mcp_personal_notification');
    }
}
