<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260611092012 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create address book tables for organizations, contacts, people and tags.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE email_contact (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, email_address VARCHAR(255) NOT NULL, label VARCHAR(255) DEFAULT NULL, opt_in_newsletter TINYINT DEFAULT 1 NOT NULL, active TINYINT DEFAULT 1 NOT NULL, source VARCHAR(32) NOT NULL, organization_id INT DEFAULT NULL, person_id INT DEFAULT NULL, INDEX idx_email_contact_organization (organization_id), INDEX idx_email_contact_person (person_id), INDEX idx_email_contact_newsletter (active, opt_in_newsletter), UNIQUE INDEX uniq_email_contact_uuid (uuid), UNIQUE INDEX uniq_email_contact_email_address (email_address), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE organization (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(32) NOT NULL, sector VARCHAR(32) NOT NULL, customer_status VARCHAR(32) NOT NULL, address LONGTEXT DEFAULT NULL, postal_code VARCHAR(5) DEFAULT NULL, city VARCHAR(255) DEFAULT NULL, active TINYINT DEFAULT 1 NOT NULL, municipality_id INT DEFAULT NULL, INDEX idx_organization_municipality (municipality_id), INDEX idx_organization_name (name), INDEX idx_organization_customer_status (customer_status), UNIQUE INDEX uniq_organization_uuid (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE organization_tag (organization_id INT NOT NULL, tag_id INT NOT NULL, INDEX IDX_904E86032C8A3DE (organization_id), INDEX IDX_904E860BAD26311 (tag_id), PRIMARY KEY (organization_id, tag_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE person (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, role VARCHAR(255) DEFAULT NULL, active TINYINT DEFAULT 1 NOT NULL, organization_id INT DEFAULT NULL, INDEX idx_person_organization (organization_id), INDEX idx_person_name (last_name, first_name), UNIQUE INDEX uniq_person_uuid (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE phone_contact (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, phone_number VARCHAR(20) NOT NULL, label VARCHAR(255) DEFAULT NULL, active TINYINT DEFAULT 1 NOT NULL, organization_id INT DEFAULT NULL, person_id INT DEFAULT NULL, INDEX idx_phone_contact_organization (organization_id), INDEX idx_phone_contact_person (person_id), INDEX idx_phone_contact_phone_number (phone_number), UNIQUE INDEX uniq_phone_contact_uuid (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE tag (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, label VARCHAR(255) NOT NULL, INDEX idx_tag_label (label), UNIQUE INDEX uniq_tag_uuid (uuid), UNIQUE INDEX uniq_tag_label (label), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE email_contact ADD CONSTRAINT FK_F1A28EF732C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_contact ADD CONSTRAINT FK_F1A28EF7217BBB47 FOREIGN KEY (person_id) REFERENCES person (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE organization ADD CONSTRAINT FK_C1EE637CAE6F181C FOREIGN KEY (municipality_id) REFERENCES municipality (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE organization_tag ADD CONSTRAINT FK_904E86032C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE organization_tag ADD CONSTRAINT FK_904E860BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE person ADD CONSTRAINT FK_34DCD17632C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE phone_contact ADD CONSTRAINT FK_DC206A0A32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE phone_contact ADD CONSTRAINT FK_DC206A0A217BBB47 FOREIGN KEY (person_id) REFERENCES person (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_contact DROP FOREIGN KEY FK_F1A28EF732C8A3DE');
        $this->addSql('ALTER TABLE email_contact DROP FOREIGN KEY FK_F1A28EF7217BBB47');
        $this->addSql('ALTER TABLE organization DROP FOREIGN KEY FK_C1EE637CAE6F181C');
        $this->addSql('ALTER TABLE organization_tag DROP FOREIGN KEY FK_904E86032C8A3DE');
        $this->addSql('ALTER TABLE organization_tag DROP FOREIGN KEY FK_904E860BAD26311');
        $this->addSql('ALTER TABLE person DROP FOREIGN KEY FK_34DCD17632C8A3DE');
        $this->addSql('ALTER TABLE phone_contact DROP FOREIGN KEY FK_DC206A0A32C8A3DE');
        $this->addSql('ALTER TABLE phone_contact DROP FOREIGN KEY FK_DC206A0A217BBB47');
        $this->addSql('DROP TABLE email_contact');
        $this->addSql('DROP TABLE organization');
        $this->addSql('DROP TABLE organization_tag');
        $this->addSql('DROP TABLE person');
        $this->addSql('DROP TABLE phone_contact');
        $this->addSql('DROP TABLE tag');
    }
}
