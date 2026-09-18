<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260904145911 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds portal accounts and multi-organization session associations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE portal_user (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) DEFAULT NULL, status VARCHAR(16) NOT NULL, active TINYINT DEFAULT 1 NOT NULL, UNIQUE INDEX uniq_portal_user_uuid (uuid), UNIQUE INDEX uniq_portal_user_email (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE session_summary_organization (session_summary_id INT NOT NULL, organization_id INT NOT NULL, INDEX IDX_9C24D62ECA54DF07 (session_summary_id), INDEX IDX_9C24D62E32C8A3DE (organization_id), PRIMARY KEY (session_summary_id, organization_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_organization_access (id INT AUTO_INCREMENT NOT NULL, active TINYINT DEFAULT 1 NOT NULL, user_id INT NOT NULL, organization_id INT NOT NULL, person_id INT DEFAULT NULL, INDEX IDX_292494D3A76ED395 (user_id), INDEX IDX_292494D332C8A3DE (organization_id), INDEX IDX_292494D3217BBB47 (person_id), UNIQUE INDEX uniq_user_organization_access (user_id, organization_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE session_summary_organization ADD CONSTRAINT FK_9C24D62ECA54DF07 FOREIGN KEY (session_summary_id) REFERENCES session_summary (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE session_summary_organization ADD CONSTRAINT FK_9C24D62E32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_organization_access ADD CONSTRAINT FK_292494D3A76ED395 FOREIGN KEY (user_id) REFERENCES portal_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_organization_access ADD CONSTRAINT FK_292494D332C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_organization_access ADD CONSTRAINT FK_292494D3217BBB47 FOREIGN KEY (person_id) REFERENCES person (id) ON DELETE SET NULL');
        $this->addSql('DROP INDEX idx_session_summary_organization ON session_summary');
        $this->addSql('ALTER TABLE session_summary DROP organization_name');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE session_summary_organization DROP FOREIGN KEY FK_9C24D62ECA54DF07');
        $this->addSql('ALTER TABLE session_summary_organization DROP FOREIGN KEY FK_9C24D62E32C8A3DE');
        $this->addSql('ALTER TABLE user_organization_access DROP FOREIGN KEY FK_292494D3A76ED395');
        $this->addSql('ALTER TABLE user_organization_access DROP FOREIGN KEY FK_292494D332C8A3DE');
        $this->addSql('ALTER TABLE user_organization_access DROP FOREIGN KEY FK_292494D3217BBB47');
        $this->addSql('DROP TABLE portal_user');
        $this->addSql('DROP TABLE session_summary_organization');
        $this->addSql('DROP TABLE user_organization_access');
        $this->addSql('ALTER TABLE session_summary ADD organization_name VARCHAR(255) NOT NULL');
        $this->addSql('CREATE INDEX idx_session_summary_organization ON session_summary (organization_name)');
    }
}
