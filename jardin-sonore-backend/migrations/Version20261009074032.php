<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009074032 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store commercial requests, projects, people, actions and history.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE commercial_action (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, due_on DATE NOT NULL, details LONGTEXT DEFAULT NULL, status VARCHAR(16) NOT NULL, resolved_at DATETIME DEFAULT NULL, project_id INT NOT NULL, person_id INT DEFAULT NULL, INDEX idx_commercial_action_status_due (status, due_on), INDEX idx_commercial_action_project (project_id), INDEX idx_commercial_action_person (person_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE commercial_event (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(48) NOT NULL, occurred_at DATETIME NOT NULL, content LONGTEXT DEFAULT NULL, metadata JSON NOT NULL, project_id INT NOT NULL, person_id INT DEFAULT NULL, INDEX IDX_14D00886166D1F9C (project_id), INDEX idx_commercial_event_project_occurred (project_id, occurred_at), INDEX idx_commercial_event_person (person_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE commercial_project (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, status VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, closed_at DATETIME DEFAULT NULL, organization_id INT NOT NULL, request_id INT DEFAULT NULL, primary_contact_id INT DEFAULT NULL, INDEX IDX_4D228B5F32C8A3DE (organization_id), INDEX idx_commercial_project_organization_status (organization_id, status), INDEX idx_commercial_project_request (request_id), INDEX idx_commercial_project_primary_contact (primary_contact_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE commercial_project_person (id INT AUTO_INCREMENT NOT NULL, linked_at DATETIME NOT NULL, current TINYINT DEFAULT 1 NOT NULL, project_id INT NOT NULL, person_id INT NOT NULL, INDEX IDX_BE7AB734166D1F9C (project_id), INDEX idx_commercial_project_person_current (project_id, current), INDEX idx_commercial_project_person_person (person_id), UNIQUE INDEX uniq_commercial_project_person_pair (project_id, person_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE commercial_request (id INT AUTO_INCREMENT NOT NULL, source VARCHAR(32) NOT NULL, sender_name VARCHAR(255) NOT NULL, email_address VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, received_at DATETIME NOT NULL, organization_name VARCHAR(255) DEFAULT NULL, city VARCHAR(255) DEFAULT NULL, phone VARCHAR(64) DEFAULT NULL, submission_key VARCHAR(64) DEFAULT NULL, status VARCHAR(32) NOT NULL, INDEX idx_commercial_request_status_received (status, received_at), UNIQUE INDEX uniq_commercial_request_submission_key (submission_key), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE commercial_action ADD CONSTRAINT FK_B8AE2CE166D1F9C FOREIGN KEY (project_id) REFERENCES commercial_project (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE commercial_action ADD CONSTRAINT FK_B8AE2CE217BBB47 FOREIGN KEY (person_id) REFERENCES person (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE commercial_event ADD CONSTRAINT FK_14D00886166D1F9C FOREIGN KEY (project_id) REFERENCES commercial_project (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE commercial_event ADD CONSTRAINT FK_14D00886217BBB47 FOREIGN KEY (person_id) REFERENCES person (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE commercial_project ADD CONSTRAINT FK_4D228B5F32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE commercial_project ADD CONSTRAINT FK_4D228B5F427EB8A5 FOREIGN KEY (request_id) REFERENCES commercial_request (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE commercial_project ADD CONSTRAINT FK_4D228B5FD905C92C FOREIGN KEY (primary_contact_id) REFERENCES person (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE commercial_project_person ADD CONSTRAINT FK_BE7AB734166D1F9C FOREIGN KEY (project_id) REFERENCES commercial_project (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE commercial_project_person ADD CONSTRAINT FK_BE7AB734217BBB47 FOREIGN KEY (person_id) REFERENCES person (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commercial_action DROP FOREIGN KEY FK_B8AE2CE166D1F9C');
        $this->addSql('ALTER TABLE commercial_action DROP FOREIGN KEY FK_B8AE2CE217BBB47');
        $this->addSql('ALTER TABLE commercial_event DROP FOREIGN KEY FK_14D00886166D1F9C');
        $this->addSql('ALTER TABLE commercial_event DROP FOREIGN KEY FK_14D00886217BBB47');
        $this->addSql('ALTER TABLE commercial_project DROP FOREIGN KEY FK_4D228B5F32C8A3DE');
        $this->addSql('ALTER TABLE commercial_project DROP FOREIGN KEY FK_4D228B5F427EB8A5');
        $this->addSql('ALTER TABLE commercial_project DROP FOREIGN KEY FK_4D228B5FD905C92C');
        $this->addSql('ALTER TABLE commercial_project_person DROP FOREIGN KEY FK_BE7AB734166D1F9C');
        $this->addSql('ALTER TABLE commercial_project_person DROP FOREIGN KEY FK_BE7AB734217BBB47');
        $this->addSql('DROP TABLE commercial_action');
        $this->addSql('DROP TABLE commercial_event');
        $this->addSql('DROP TABLE commercial_project');
        $this->addSql('DROP TABLE commercial_project_person');
        $this->addSql('DROP TABLE commercial_request');
    }
}
