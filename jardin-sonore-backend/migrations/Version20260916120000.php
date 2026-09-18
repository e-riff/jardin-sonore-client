<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds secure single-use password tokens for portal accounts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE portal_user_password_token (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, type VARCHAR(16) NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', consumed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', invalidated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_portal_user_password_token_hash (token_hash), INDEX idx_portal_user_password_token_user_type (user_id, type), INDEX IDX_78EEBC48A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE portal_user_password_token ADD CONSTRAINT FK_FD931A7BA76ED395 FOREIGN KEY (user_id) REFERENCES portal_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE portal_user_password_token DROP FOREIGN KEY FK_FD931A7BA76ED395');
        $this->addSql('DROP TABLE portal_user_password_token');
    }
}
