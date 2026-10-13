<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013132221 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow provisional commercial dossiers and track quote refusal and event sources.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commercial_event ADD action_id INT DEFAULT NULL, ADD quote_id INT DEFAULT NULL, ADD invoice_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE commercial_event ADD CONSTRAINT FK_14D008869D32F035 FOREIGN KEY (action_id) REFERENCES commercial_action (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE commercial_event ADD CONSTRAINT FK_14D00886DB805178 FOREIGN KEY (quote_id) REFERENCES commercial_quote (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE commercial_event ADD CONSTRAINT FK_14D008862989F1FD FOREIGN KEY (invoice_id) REFERENCES commercial_invoice (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX idx_commercial_event_action ON commercial_event (action_id)');
        $this->addSql('CREATE INDEX idx_commercial_event_quote ON commercial_event (quote_id)');
        $this->addSql('CREATE INDEX idx_commercial_event_invoice ON commercial_event (invoice_id)');
        $this->addSql('ALTER TABLE commercial_project CHANGE organization_id organization_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE commercial_quote ADD refused_on DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(0 < (int) $this->connection->fetchOne('SELECT COUNT(*) FROM commercial_project WHERE organization_id IS NULL'), 'Attach provisional dossiers to an organization before rolling this migration back.');
        $this->addSql('ALTER TABLE commercial_event DROP FOREIGN KEY FK_14D008869D32F035');
        $this->addSql('ALTER TABLE commercial_event DROP FOREIGN KEY FK_14D00886DB805178');
        $this->addSql('ALTER TABLE commercial_event DROP FOREIGN KEY FK_14D008862989F1FD');
        $this->addSql('DROP INDEX idx_commercial_event_action ON commercial_event');
        $this->addSql('DROP INDEX idx_commercial_event_quote ON commercial_event');
        $this->addSql('DROP INDEX idx_commercial_event_invoice ON commercial_event');
        $this->addSql('ALTER TABLE commercial_event DROP action_id, DROP quote_id, DROP invoice_id');
        $this->addSql('ALTER TABLE commercial_project CHANGE organization_id organization_id INT NOT NULL');
        $this->addSql('ALTER TABLE commercial_quote DROP refused_on');
    }
}
