<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924071429 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Synchronize portal schema metadata with Doctrine mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE portal_impersonation_launch RENAME INDEX uniq_portal_impersonation_launch_token_hash TO UNIQ_76E1D147B3BC57DA');
        $this->addSql('ALTER TABLE portal_impersonation_launch RENAME INDEX idx_portal_impersonation_launch_user TO IDX_76E1D147A76ED395');
        $this->addSql('ALTER TABLE portal_impersonation_launch RENAME INDEX idx_portal_impersonation_launch_issued_by TO IDX_76E1D147784BB717');
        $this->addSql('ALTER TABLE portal_user_password_token CHANGE expires_at expires_at DATETIME NOT NULL, CHANGE consumed_at consumed_at DATETIME DEFAULT NULL, CHANGE invalidated_at invalidated_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE portal_impersonation_launch RENAME INDEX idx_76e1d147a76ed395 TO IDX_PORTAL_IMPERSONATION_LAUNCH_USER');
        $this->addSql('ALTER TABLE portal_impersonation_launch RENAME INDEX idx_76e1d147784bb717 TO IDX_PORTAL_IMPERSONATION_LAUNCH_ISSUED_BY');
        $this->addSql('ALTER TABLE portal_impersonation_launch RENAME INDEX uniq_76e1d147b3bc57da TO UNIQ_PORTAL_IMPERSONATION_LAUNCH_TOKEN_HASH');
        $this->addSql('ALTER TABLE portal_user_password_token CHANGE expires_at expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE consumed_at consumed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE invalidated_at invalidated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
