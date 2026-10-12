<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012075703 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add optional preheader and introduction blocks to mailing campaigns without changing existing content.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mailing_campaign ADD preheader VARCHAR(255) DEFAULT NULL, ADD intro_blocks JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mailing_campaign DROP preheader, DROP intro_blocks');
    }
}
