<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260610150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create geography reference tables for regions, departments and municipalities.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE region (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(150) NOT NULL, code VARCHAR(3) NOT NULL, UNIQUE INDEX uniq_region_uuid (uuid), UNIQUE INDEX uniq_region_code (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE department (id INT AUTO_INCREMENT NOT NULL, region_id INT NOT NULL, uuid BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(150) NOT NULL, code VARCHAR(3) NOT NULL, INDEX idx_department_region (region_id), UNIQUE INDEX uniq_department_uuid (uuid), UNIQUE INDEX uniq_department_code (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE municipality (id INT AUTO_INCREMENT NOT NULL, department_id INT NOT NULL, uuid BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(255) NOT NULL, phone_number VARCHAR(20) DEFAULT NULL, email_address VARCHAR(255) DEFAULT NULL, address LONGTEXT DEFAULT NULL, postal_code VARCHAR(5) DEFAULT NULL, insee_code VARCHAR(5) DEFAULT NULL, siren VARCHAR(9) DEFAULT NULL, siret VARCHAR(14) DEFAULT NULL, geo_shape JSON DEFAULT NULL, INDEX idx_municipality_department (department_id), INDEX idx_municipality_postal_code (postal_code), UNIQUE INDEX uniq_municipality_uuid (uuid), UNIQUE INDEX uniq_municipality_insee_code (insee_code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE department ADD CONSTRAINT FK_CD1DE18A98260155 FOREIGN KEY (region_id) REFERENCES region (id)');
        $this->addSql('ALTER TABLE municipality ADD CONSTRAINT FK_B22306EBAE80F5DF FOREIGN KEY (department_id) REFERENCES department (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE municipality DROP FOREIGN KEY FK_B22306EBAE80F5DF');
        $this->addSql('ALTER TABLE department DROP FOREIGN KEY FK_CD1DE18A98260155');
        $this->addSql('DROP TABLE municipality');
        $this->addSql('DROP TABLE department');
        $this->addSql('DROP TABLE region');
    }
}
