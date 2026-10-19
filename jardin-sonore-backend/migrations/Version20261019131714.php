<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261019131714 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track the pending IMAP draft while a verified replacement is prepared.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mcp_mail_draft ADD pending_message_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mcp_mail_draft DROP pending_message_id');
    }
}
