<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930071935 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track first session publication and initialize already published sessions without sending notifications.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE session_summary ADD first_published_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE session_summary SET first_published_at = updated_at WHERE published = 1 AND first_published_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE session_summary DROP first_published_at');
    }
}
