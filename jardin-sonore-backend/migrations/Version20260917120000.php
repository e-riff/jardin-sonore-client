<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260917120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create hashed, one-time portal impersonation launch records.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE portal_impersonation_launch (id INT AUTO_INCREMENT NOT NULL, token_hash VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, consumed_at DATETIME DEFAULT NULL, invalidated_at DATETIME DEFAULT NULL, user_id INT NOT NULL, issued_by_id INT NOT NULL, UNIQUE INDEX UNIQ_PORTAL_IMPERSONATION_LAUNCH_TOKEN_HASH (token_hash), INDEX IDX_PORTAL_IMPERSONATION_LAUNCH_USER (user_id), INDEX IDX_PORTAL_IMPERSONATION_LAUNCH_ISSUED_BY (issued_by_id), INDEX idx_portal_impersonation_launch_user_expiry (user_id, expires_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE portal_impersonation_launch ADD CONSTRAINT FK_PORTAL_IMPERSONATION_LAUNCH_USER FOREIGN KEY (user_id) REFERENCES portal_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE portal_impersonation_launch ADD CONSTRAINT FK_PORTAL_IMPERSONATION_LAUNCH_ISSUED_BY FOREIGN KEY (issued_by_id) REFERENCES admin_user (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE portal_impersonation_launch DROP FOREIGN KEY FK_PORTAL_IMPERSONATION_LAUNCH_USER');
        $this->addSql('ALTER TABLE portal_impersonation_launch DROP FOREIGN KEY FK_PORTAL_IMPERSONATION_LAUNCH_ISSUED_BY');
        $this->addSql('DROP TABLE portal_impersonation_launch');
    }
}
