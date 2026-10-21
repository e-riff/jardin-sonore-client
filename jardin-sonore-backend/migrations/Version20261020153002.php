<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020153002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store dossier appointments linked to Google Calendar with revision, status and summary.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE commercial_appointment (id INT AUTO_INCREMENT NOT NULL, calendar_id VARCHAR(255) NOT NULL, google_event_id VARCHAR(255) NOT NULL, request_id VARCHAR(32) NOT NULL, title VARCHAR(255) NOT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, time_zone VARCHAR(64) NOT NULL, location LONGTEXT DEFAULT NULL, etag VARCHAR(255) NOT NULL, synced_at DATETIME NOT NULL, status VARCHAR(16) NOT NULL, canceled_at DATETIME DEFAULT NULL, summary LONGTEXT DEFAULT NULL, sync_error VARCHAR(64) DEFAULT NULL, revision INT NOT NULL, project_id INT NOT NULL, INDEX IDX_F0F9ABC5166D1F9C (project_id), INDEX idx_commercial_appointment_project_start (project_id, starts_at), UNIQUE INDEX uniq_commercial_appointment_google_event (calendar_id, google_event_id), UNIQUE INDEX uniq_commercial_appointment_request (calendar_id, request_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE commercial_appointment ADD CONSTRAINT FK_F0F9ABC5166D1F9C FOREIGN KEY (project_id) REFERENCES commercial_project (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commercial_appointment DROP FOREIGN KEY FK_F0F9ABC5166D1F9C');
        $this->addSql('DROP TABLE commercial_appointment');
    }
}
