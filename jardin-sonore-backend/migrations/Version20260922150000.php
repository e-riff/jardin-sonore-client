<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260922150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add portal account profile fields.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE portal_user ADD first_name VARCHAR(100) DEFAULT NULL, ADD last_name VARCHAR(100) DEFAULT NULL, ADD avatar_path VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE portal_user DROP first_name, DROP last_name, DROP avatar_path');
    }
}
