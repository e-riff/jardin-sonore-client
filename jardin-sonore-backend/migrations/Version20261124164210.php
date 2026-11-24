<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124164210 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les familles de séances et leurs catégories.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE session_family (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, name VARCHAR(255) NOT NULL, UNIQUE INDEX uniq_session_family_uuid (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE session_family_theme (family_id INT NOT NULL, theme_id INT NOT NULL, INDEX IDX_659FC9FAC35E566A (family_id), INDEX IDX_659FC9FA59027487 (theme_id), PRIMARY KEY (family_id, theme_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE session_family_theme ADD CONSTRAINT FK_659FC9FAC35E566A FOREIGN KEY (family_id) REFERENCES session_family (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE session_family_theme ADD CONSTRAINT FK_659FC9FA59027487 FOREIGN KEY (theme_id) REFERENCES theme (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE session_summary ADD family_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE session_summary ADD CONSTRAINT FK_DCF0E439C35E566A FOREIGN KEY (family_id) REFERENCES session_family (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX IDX_DCF0E439C35E566A ON session_summary (family_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE session_summary DROP FOREIGN KEY FK_DCF0E439C35E566A');
        $this->addSql('DROP INDEX IDX_DCF0E439C35E566A ON session_summary');
        $this->addSql('ALTER TABLE session_summary DROP family_id');
        $this->addSql('ALTER TABLE session_family_theme DROP FOREIGN KEY FK_659FC9FAC35E566A');
        $this->addSql('ALTER TABLE session_family_theme DROP FOREIGN KEY FK_659FC9FA59027487');
        $this->addSql('DROP TABLE session_family_theme');
        $this->addSql('DROP TABLE session_family');
    }
}
