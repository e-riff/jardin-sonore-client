<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260623110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace unknown address book enum values with nullable fields.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization CHANGE type type VARCHAR(32) DEFAULT NULL, CHANGE sector sector VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE directory_entry CHANGE customer_status customer_status VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE email_contact CHANGE source source VARCHAR(32) DEFAULT NULL');

        $this->addSql('UPDATE organization SET type = NULL WHERE type = \'unknown\'');
        $this->addSql('UPDATE organization SET sector = NULL WHERE sector = \'unknown\'');
        $this->addSql('UPDATE directory_entry SET customer_status = NULL WHERE customer_status = \'unknown\'');
        $this->addSql('UPDATE email_contact SET source = NULL WHERE source = \'unknown\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE organization SET type = \'unknown\' WHERE type IS NULL');
        $this->addSql('UPDATE organization SET sector = \'unknown\' WHERE sector IS NULL');
        $this->addSql('UPDATE directory_entry SET customer_status = \'unknown\' WHERE customer_status IS NULL');
        $this->addSql('UPDATE email_contact SET source = \'unknown\' WHERE source IS NULL');

        $this->addSql('ALTER TABLE organization CHANGE type type VARCHAR(32) NOT NULL, CHANGE sector sector VARCHAR(32) NOT NULL');
        $this->addSql('ALTER TABLE directory_entry CHANGE customer_status customer_status VARCHAR(32) NOT NULL');
        $this->addSql('ALTER TABLE email_contact CHANGE source source VARCHAR(32) NOT NULL');
    }
}
