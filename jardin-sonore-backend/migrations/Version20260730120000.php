<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260730120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add canonical document lifecycle fields to session summaries.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE session_summary ADD document_status VARCHAR(20) NOT NULL DEFAULT 'pending', ADD document_path VARCHAR(500) DEFAULT NULL, ADD document_error LONGTEXT DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE session_summary DROP document_status, DROP document_path, DROP document_error');
    }
}
