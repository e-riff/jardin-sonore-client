<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009083953 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track issued invoices, payments and a single overdue reminder action.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE commercial_invoice (id INT AUTO_INCREMENT NOT NULL, reference VARCHAR(64) NOT NULL, amount_cents INT NOT NULL, issued_on DATE NOT NULL, paid_on DATE DEFAULT NULL, project_id INT NOT NULL, reminder_action_id INT DEFAULT NULL, INDEX idx_commercial_invoice_project (project_id), UNIQUE INDEX uniq_commercial_invoice_reference (reference), UNIQUE INDEX uniq_commercial_invoice_reminder_action (reminder_action_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE commercial_invoice ADD CONSTRAINT FK_F2F44CF5166D1F9C FOREIGN KEY (project_id) REFERENCES commercial_project (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE commercial_invoice ADD CONSTRAINT FK_F2F44CF5843CB543 FOREIGN KEY (reminder_action_id) REFERENCES commercial_action (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commercial_invoice DROP FOREIGN KEY FK_F2F44CF5166D1F9C');
        $this->addSql('ALTER TABLE commercial_invoice DROP FOREIGN KEY FK_F2F44CF5843CB543');
        $this->addSql('DROP TABLE commercial_invoice');
    }
}
