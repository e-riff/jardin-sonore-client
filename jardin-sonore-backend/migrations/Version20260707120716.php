<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260707120716 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create instrument catalog tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE instrument (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, name VARCHAR(255) NOT NULL, tuning VARCHAR(80) DEFAULT NULL, quantity INT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_instrument_name (name), INDEX idx_instrument_active (active), INDEX idx_instrument_updated_at (updated_at), UNIQUE INDEX uniq_instrument_uuid (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE instrument_instrument_tag (instrument_id INT NOT NULL, instrument_tag_id INT NOT NULL, INDEX IDX_C24ADC89CF11D9C (instrument_id), INDEX IDX_C24ADC898E646059 (instrument_tag_id), PRIMARY KEY (instrument_id, instrument_tag_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE instrument_tag (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, label VARCHAR(255) NOT NULL, INDEX idx_instrument_tag_label (label), UNIQUE INDEX uniq_instrument_tag_uuid (uuid), UNIQUE INDEX uniq_instrument_tag_label (label), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE instrument_instrument_tag ADD CONSTRAINT FK_C24ADC89CF11D9C FOREIGN KEY (instrument_id) REFERENCES instrument (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE instrument_instrument_tag ADD CONSTRAINT FK_C24ADC898E646059 FOREIGN KEY (instrument_tag_id) REFERENCES instrument_tag (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE instrument_instrument_tag DROP FOREIGN KEY FK_C24ADC89CF11D9C');
        $this->addSql('ALTER TABLE instrument_instrument_tag DROP FOREIGN KEY FK_C24ADC898E646059');
        $this->addSql('DROP TABLE instrument');
        $this->addSql('DROP TABLE instrument_instrument_tag');
        $this->addSql('DROP TABLE instrument_tag');
    }
}
