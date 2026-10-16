<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016084010 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add commercial calendar tables and invoice preparation tracking.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE commercial_intervention (id INT AUTO_INCREMENT NOT NULL, calendar_id VARCHAR(255) NOT NULL, google_event_id VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, time_zone VARCHAR(64) NOT NULL, location LONGTEXT DEFAULT NULL, etag VARCHAR(255) NOT NULL, synced_at DATETIME NOT NULL, recurring_event_id VARCHAR(255) DEFAULT NULL, original_starts_at DATETIME DEFAULT NULL, status VARCHAR(16) NOT NULL, canceled_at DATETIME DEFAULT NULL, sync_error LONGTEXT DEFAULT NULL, project_id INT DEFAULT NULL, INDEX IDX_4BA9664E166D1F9C (project_id), INDEX idx_commercial_intervention_project_start (project_id, starts_at), INDEX idx_commercial_intervention_status_end (status, ends_at), UNIQUE INDEX uniq_commercial_intervention_google_event (calendar_id, google_event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE commercial_intervention ADD CONSTRAINT FK_4BA9664E166D1F9C FOREIGN KEY (project_id) REFERENCES commercial_project (id) ON DELETE SET NULL');
        $this->addSql('CREATE TABLE google_calendar_connection (id INT NOT NULL, encrypted_refresh_token LONGTEXT NOT NULL, account_email VARCHAR(255) NOT NULL, connected_at DATETIME NOT NULL, last_success_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE commercial_invoice ADD service_month DATE DEFAULT NULL');
        $this->addSql('UPDATE commercial_invoice SET service_month = DATE_SUB(issued_on, INTERVAL DAYOFMONTH(issued_on) - 1 DAY)');
        $this->addSql('ALTER TABLE commercial_invoice MODIFY service_month DATE NOT NULL');
        $this->addSql('CREATE TABLE commercial_invoice_preparation (id INT AUTO_INCREMENT NOT NULL, service_month DATE NOT NULL, project_id INT NOT NULL, action_id INT NOT NULL, INDEX IDX_696CDD9166D1F9C (project_id), UNIQUE INDEX uniq_commercial_invoice_preparation_project_month (project_id, service_month), UNIQUE INDEX uniq_commercial_invoice_preparation_action (action_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE commercial_invoice_preparation ADD CONSTRAINT FK_696CDD9166D1F9C FOREIGN KEY (project_id) REFERENCES commercial_project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE commercial_invoice_preparation ADD CONSTRAINT FK_696CDD99D32F035 FOREIGN KEY (action_id) REFERENCES commercial_action (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commercial_invoice_preparation DROP FOREIGN KEY FK_696CDD9166D1F9C');
        $this->addSql('ALTER TABLE commercial_invoice_preparation DROP FOREIGN KEY FK_696CDD99D32F035');
        $this->addSql('DROP TABLE commercial_invoice_preparation');
        $this->addSql('ALTER TABLE commercial_invoice DROP service_month');
        $this->addSql('DROP TABLE google_calendar_connection');
        $this->addSql('ALTER TABLE commercial_intervention DROP FOREIGN KEY FK_4BA9664E166D1F9C');
        $this->addSql('DROP TABLE commercial_intervention');
    }
}
