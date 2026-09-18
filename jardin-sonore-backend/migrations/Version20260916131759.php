<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260916131759 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create opaque portal sessions for authenticated structure accounts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE portal_session (id INT AUTO_INCREMENT NOT NULL, token_hash VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, revoked_at DATETIME DEFAULT NULL, user_id INT NOT NULL, impersonated_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_D1810DCDB3BC57DA (token_hash), INDEX IDX_D1810DCDA76ED395 (user_id), INDEX IDX_D1810DCD689DCFD (impersonated_by_id), INDEX idx_portal_session_user_expiry (user_id, expires_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE portal_session ADD CONSTRAINT FK_D1810DCDA76ED395 FOREIGN KEY (user_id) REFERENCES portal_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE portal_session ADD CONSTRAINT FK_D1810DCD689DCFD FOREIGN KEY (impersonated_by_id) REFERENCES admin_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE portal_session DROP FOREIGN KEY FK_D1810DCDA76ED395');
        $this->addSql('ALTER TABLE portal_session DROP FOREIGN KEY FK_D1810DCD689DCFD');
        $this->addSql('DROP TABLE portal_session');
    }
}
