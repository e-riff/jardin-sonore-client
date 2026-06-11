<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611130710 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create admin user table for backend authentication.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE admin_user (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, active TINYINT DEFAULT 1 NOT NULL, UNIQUE INDEX uniq_admin_user_uuid (uuid), UNIQUE INDEX uniq_admin_user_email (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE admin_user');
    }
}
