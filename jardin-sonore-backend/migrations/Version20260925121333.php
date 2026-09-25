<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260925121333 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add portal publication to sessions, keeping existing sessions visible.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE session_summary ADD published TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE session_summary SET published = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE session_summary DROP published');
    }
}
