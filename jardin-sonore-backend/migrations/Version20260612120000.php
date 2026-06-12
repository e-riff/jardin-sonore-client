<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move people and organizations to joined directory entries with shared contact details.';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('SET @person_id_offset = (SELECT COALESCE(MAX(id), 0) FROM organization)');

        $this->addSql('ALTER TABLE email_contact DROP FOREIGN KEY FK_F1A28EF732C8A3DE');
        $this->addSql('ALTER TABLE email_contact DROP FOREIGN KEY FK_F1A28EF7217BBB47');
        $this->addSql('ALTER TABLE phone_contact DROP FOREIGN KEY FK_DC206A0A32C8A3DE');
        $this->addSql('ALTER TABLE phone_contact DROP FOREIGN KEY FK_DC206A0A217BBB47');
        $this->addSql('ALTER TABLE organization DROP FOREIGN KEY FK_C1EE637CAE6F181C');
        $this->addSql('ALTER TABLE person DROP FOREIGN KEY FK_34DCD17632C8A3DE');
        $this->addSql('ALTER TABLE organization_tag DROP FOREIGN KEY FK_904E86032C8A3DE');
        $this->addSql('ALTER TABLE organization_tag DROP FOREIGN KEY FK_904E860BAD26311');

        $this->addSql('CREATE TABLE directory_entry (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, discriminator VARCHAR(32) NOT NULL, entry_type VARCHAR(32) NOT NULL, customer_status VARCHAR(32) NOT NULL, active TINYINT DEFAULT 1 NOT NULL, INDEX idx_directory_entry_type (entry_type), INDEX idx_directory_entry_customer_status (customer_status), UNIQUE INDEX uniq_directory_entry_uuid (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('INSERT INTO directory_entry (id, uuid, discriminator, entry_type, customer_status, active) SELECT id, uuid, \'organization\', \'organization\', customer_status, active FROM organization');
        $this->addSql('INSERT INTO directory_entry (id, uuid, discriminator, entry_type, customer_status, active) SELECT id + @person_id_offset, uuid, \'person\', \'person\', \'unknown\', active FROM person');

        $this->addSql('UPDATE email_contact SET person_id = person_id + @person_id_offset WHERE person_id IS NOT NULL');
        $this->addSql('UPDATE phone_contact SET person_id = person_id + @person_id_offset WHERE person_id IS NOT NULL');
        $this->addSql('UPDATE person SET id = id + @person_id_offset ORDER BY id DESC');
        $this->addSql('ALTER TABLE organization CHANGE id id INT NOT NULL');
        $this->addSql('ALTER TABLE person CHANGE id id INT NOT NULL');

        $this->addSql('RENAME TABLE organization_tag TO directory_entry_tag');
        $this->addSql('ALTER TABLE directory_entry_tag CHANGE organization_id directory_entry_id INT NOT NULL');
        $this->addSql('DROP INDEX IDX_904E86032C8A3DE ON directory_entry_tag');
        $this->addSql('DROP INDEX IDX_904E860BAD26311 ON directory_entry_tag');
        $this->addSql('CREATE INDEX IDX_2E8AEF15BE8E7CAF ON directory_entry_tag (directory_entry_id)');
        $this->addSql('CREATE INDEX IDX_2E8AEF15BAD26311 ON directory_entry_tag (tag_id)');
        $this->addSql('ALTER TABLE directory_entry_tag ADD CONSTRAINT FK_DIRECTORY_ENTRY_TAG_ENTRY FOREIGN KEY (directory_entry_id) REFERENCES directory_entry (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE directory_entry_tag ADD CONSTRAINT FK_DIRECTORY_ENTRY_TAG_TAG FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE contact_details (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, directory_entry_id INT NOT NULL, INDEX idx_contact_details_directory_entry (directory_entry_id), UNIQUE INDEX uniq_contact_details_uuid (uuid), UNIQUE INDEX uniq_contact_details_directory_entry (directory_entry_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE contact_details ADD CONSTRAINT FK_CONTACT_DETAILS_DIRECTORY_ENTRY FOREIGN KEY (directory_entry_id) REFERENCES directory_entry (id) ON DELETE CASCADE');
        $this->addSql('INSERT INTO contact_details (uuid, directory_entry_id) SELECT UNHEX(REPLACE(UUID(), \'-\', \'\')), id FROM directory_entry');

        $this->addSql('CREATE TABLE address_contact (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, contact_details_id INT NOT NULL, municipality_id INT DEFAULT NULL, type VARCHAR(32) NOT NULL, label VARCHAR(255) DEFAULT NULL, address LONGTEXT DEFAULT NULL, postal_code VARCHAR(5) DEFAULT NULL, city VARCHAR(255) DEFAULT NULL, active TINYINT DEFAULT 1 NOT NULL, INDEX idx_address_contact_details (contact_details_id), INDEX idx_address_contact_municipality (municipality_id), INDEX idx_address_contact_postal_code (postal_code), UNIQUE INDEX uniq_address_contact_uuid (uuid), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE address_contact ADD CONSTRAINT FK_ADDRESS_CONTACT_CONTACT_DETAILS FOREIGN KEY (contact_details_id) REFERENCES contact_details (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE address_contact ADD CONSTRAINT FK_ADDRESS_CONTACT_MUNICIPALITY FOREIGN KEY (municipality_id) REFERENCES municipality (id) ON DELETE SET NULL');
        $this->addSql('INSERT INTO address_contact (uuid, contact_details_id, municipality_id, type, address, postal_code, city, active) SELECT UNHEX(REPLACE(UUID(), \'-\', \'\')), contact_details.id, organization.municipality_id, \'main\', organization.address, organization.postal_code, organization.city, 1 FROM organization INNER JOIN contact_details ON contact_details.directory_entry_id = organization.id WHERE organization.address IS NOT NULL OR organization.postal_code IS NOT NULL OR organization.city IS NOT NULL OR organization.municipality_id IS NOT NULL');

        $this->addSql('ALTER TABLE email_contact ADD contact_details_id INT DEFAULT NULL, ADD type VARCHAR(32) DEFAULT \'main\' NOT NULL');
        $this->addSql('ALTER TABLE phone_contact ADD contact_details_id INT DEFAULT NULL, ADD type VARCHAR(32) DEFAULT \'main\' NOT NULL');
        $this->addSql('UPDATE email_contact INNER JOIN contact_details ON contact_details.directory_entry_id = email_contact.organization_id SET email_contact.contact_details_id = contact_details.id WHERE email_contact.organization_id IS NOT NULL');
        $this->addSql('UPDATE email_contact INNER JOIN contact_details ON contact_details.directory_entry_id = email_contact.person_id SET email_contact.contact_details_id = contact_details.id WHERE email_contact.person_id IS NOT NULL');
        $this->addSql('UPDATE phone_contact INNER JOIN contact_details ON contact_details.directory_entry_id = phone_contact.organization_id SET phone_contact.contact_details_id = contact_details.id WHERE phone_contact.organization_id IS NOT NULL');
        $this->addSql('UPDATE phone_contact INNER JOIN contact_details ON contact_details.directory_entry_id = phone_contact.person_id SET phone_contact.contact_details_id = contact_details.id WHERE phone_contact.person_id IS NOT NULL');

        $this->addSql('DROP INDEX idx_email_contact_organization ON email_contact');
        $this->addSql('DROP INDEX idx_email_contact_person ON email_contact');
        $this->addSql('DROP INDEX idx_phone_contact_organization ON phone_contact');
        $this->addSql('DROP INDEX idx_phone_contact_person ON phone_contact');
        $this->addSql('ALTER TABLE email_contact DROP organization_id, DROP person_id, MODIFY contact_details_id INT NOT NULL');
        $this->addSql('ALTER TABLE phone_contact DROP organization_id, DROP person_id, MODIFY contact_details_id INT NOT NULL');
        $this->addSql('ALTER TABLE email_contact CHANGE type type VARCHAR(32) NOT NULL');
        $this->addSql('ALTER TABLE phone_contact CHANGE type type VARCHAR(32) NOT NULL');
        $this->addSql('CREATE INDEX idx_email_contact_details ON email_contact (contact_details_id)');
        $this->addSql('CREATE INDEX idx_phone_contact_details ON phone_contact (contact_details_id)');
        $this->addSql('ALTER TABLE email_contact ADD CONSTRAINT FK_EMAIL_CONTACT_CONTACT_DETAILS FOREIGN KEY (contact_details_id) REFERENCES contact_details (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE phone_contact ADD CONSTRAINT FK_PHONE_CONTACT_CONTACT_DETAILS FOREIGN KEY (contact_details_id) REFERENCES contact_details (id) ON DELETE CASCADE');

        $this->addSql('DROP INDEX idx_organization_municipality ON organization');
        $this->addSql('DROP INDEX idx_organization_customer_status ON organization');
        $this->addSql('DROP INDEX uniq_organization_uuid ON organization');
        $this->addSql('DROP INDEX uniq_person_uuid ON person');
        $this->addSql('ALTER TABLE organization DROP uuid, DROP customer_status, DROP address, DROP postal_code, DROP city, DROP active, DROP municipality_id');
        $this->addSql('ALTER TABLE person DROP uuid, DROP active');
        $this->addSql('ALTER TABLE organization ADD CONSTRAINT FK_ORGANIZATION_DIRECTORY_ENTRY FOREIGN KEY (id) REFERENCES directory_entry (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE person ADD CONSTRAINT FK_PERSON_DIRECTORY_ENTRY FOREIGN KEY (id) REFERENCES directory_entry (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE person ADD CONSTRAINT FK_34DCD17632C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE person DROP FOREIGN KEY FK_34DCD17632C8A3DE');
        $this->addSql('ALTER TABLE organization DROP FOREIGN KEY FK_ORGANIZATION_DIRECTORY_ENTRY');
        $this->addSql('ALTER TABLE person DROP FOREIGN KEY FK_PERSON_DIRECTORY_ENTRY');
        $this->addSql('ALTER TABLE email_contact DROP FOREIGN KEY FK_EMAIL_CONTACT_CONTACT_DETAILS');
        $this->addSql('ALTER TABLE phone_contact DROP FOREIGN KEY FK_PHONE_CONTACT_CONTACT_DETAILS');
        $this->addSql('ALTER TABLE directory_entry_tag DROP FOREIGN KEY FK_DIRECTORY_ENTRY_TAG_ENTRY');
        $this->addSql('ALTER TABLE directory_entry_tag DROP FOREIGN KEY FK_DIRECTORY_ENTRY_TAG_TAG');
        $this->addSql('ALTER TABLE address_contact DROP FOREIGN KEY FK_ADDRESS_CONTACT_CONTACT_DETAILS');
        $this->addSql('ALTER TABLE address_contact DROP FOREIGN KEY FK_ADDRESS_CONTACT_MUNICIPALITY');
        $this->addSql('ALTER TABLE contact_details DROP FOREIGN KEY FK_CONTACT_DETAILS_DIRECTORY_ENTRY');

        $this->addSql('ALTER TABLE organization ADD uuid BINARY(16) DEFAULT NULL, ADD customer_status VARCHAR(32) DEFAULT \'unknown\' NOT NULL, ADD address LONGTEXT DEFAULT NULL, ADD postal_code VARCHAR(5) DEFAULT NULL, ADD city VARCHAR(255) DEFAULT NULL, ADD active TINYINT DEFAULT 1 NOT NULL, ADD municipality_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE person ADD uuid BINARY(16) DEFAULT NULL, ADD active TINYINT DEFAULT 1 NOT NULL');
        $this->addSql('UPDATE organization INNER JOIN directory_entry ON directory_entry.id = organization.id SET organization.uuid = directory_entry.uuid, organization.customer_status = directory_entry.customer_status, organization.active = directory_entry.active');
        $this->addSql('UPDATE organization INNER JOIN contact_details ON contact_details.directory_entry_id = organization.id INNER JOIN address_contact ON address_contact.contact_details_id = contact_details.id SET organization.address = address_contact.address, organization.postal_code = address_contact.postal_code, organization.city = address_contact.city, organization.municipality_id = address_contact.municipality_id WHERE address_contact.type = \'main\'');
        $this->addSql('UPDATE person INNER JOIN directory_entry ON directory_entry.id = person.id SET person.uuid = directory_entry.uuid, person.active = directory_entry.active');

        $this->addSql('SET @person_id_offset = (SELECT COALESCE(MAX(id), 0) FROM organization)');
        $this->addSql('DROP INDEX idx_email_contact_details ON email_contact');
        $this->addSql('DROP INDEX idx_phone_contact_details ON phone_contact');
        $this->addSql('ALTER TABLE email_contact ADD organization_id INT DEFAULT NULL, ADD person_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE phone_contact ADD organization_id INT DEFAULT NULL, ADD person_id INT DEFAULT NULL');
        $this->addSql('UPDATE email_contact INNER JOIN contact_details ON contact_details.id = email_contact.contact_details_id INNER JOIN directory_entry ON directory_entry.id = contact_details.directory_entry_id SET email_contact.organization_id = directory_entry.id WHERE directory_entry.entry_type = \'organization\'');
        $this->addSql('UPDATE email_contact INNER JOIN contact_details ON contact_details.id = email_contact.contact_details_id INNER JOIN directory_entry ON directory_entry.id = contact_details.directory_entry_id SET email_contact.person_id = directory_entry.id - @person_id_offset WHERE directory_entry.entry_type = \'person\'');
        $this->addSql('UPDATE phone_contact INNER JOIN contact_details ON contact_details.id = phone_contact.contact_details_id INNER JOIN directory_entry ON directory_entry.id = contact_details.directory_entry_id SET phone_contact.organization_id = directory_entry.id WHERE directory_entry.entry_type = \'organization\'');
        $this->addSql('UPDATE phone_contact INNER JOIN contact_details ON contact_details.id = phone_contact.contact_details_id INNER JOIN directory_entry ON directory_entry.id = contact_details.directory_entry_id SET phone_contact.person_id = directory_entry.id - @person_id_offset WHERE directory_entry.entry_type = \'person\'');
        $this->addSql('ALTER TABLE email_contact DROP contact_details_id, DROP type');
        $this->addSql('ALTER TABLE phone_contact DROP contact_details_id, DROP type');

        $this->addSql('UPDATE person SET id = id - @person_id_offset ORDER BY id ASC');
        $this->addSql('ALTER TABLE organization MODIFY uuid BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE person MODIFY uuid BINARY(16) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_organization_uuid ON organization (uuid)');
        $this->addSql('CREATE UNIQUE INDEX uniq_person_uuid ON person (uuid)');
        $this->addSql('CREATE INDEX idx_organization_municipality ON organization (municipality_id)');
        $this->addSql('CREATE INDEX idx_organization_customer_status ON organization (customer_status)');
        $this->addSql('CREATE INDEX idx_email_contact_organization ON email_contact (organization_id)');
        $this->addSql('CREATE INDEX idx_email_contact_person ON email_contact (person_id)');
        $this->addSql('CREATE INDEX idx_phone_contact_organization ON phone_contact (organization_id)');
        $this->addSql('CREATE INDEX idx_phone_contact_person ON phone_contact (person_id)');
        $this->addSql('ALTER TABLE email_contact ADD CONSTRAINT FK_F1A28EF732C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_contact ADD CONSTRAINT FK_F1A28EF7217BBB47 FOREIGN KEY (person_id) REFERENCES person (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE organization ADD CONSTRAINT FK_C1EE637CAE6F181C FOREIGN KEY (municipality_id) REFERENCES municipality (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE person ADD CONSTRAINT FK_34DCD17632C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE phone_contact ADD CONSTRAINT FK_DC206A0A32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE phone_contact ADD CONSTRAINT FK_DC206A0A217BBB47 FOREIGN KEY (person_id) REFERENCES person (id) ON DELETE SET NULL');

        $this->addSql('DROP TABLE address_contact');
        $this->addSql('DROP TABLE contact_details');
        $this->addSql('RENAME TABLE directory_entry_tag TO organization_tag');
        $this->addSql('ALTER TABLE organization_tag CHANGE directory_entry_id organization_id INT NOT NULL');
        $this->addSql('DROP INDEX IDX_2E8AEF15BE8E7CAF ON organization_tag');
        $this->addSql('DROP INDEX IDX_2E8AEF15BAD26311 ON organization_tag');
        $this->addSql('CREATE INDEX IDX_904E86032C8A3DE ON organization_tag (organization_id)');
        $this->addSql('CREATE INDEX IDX_904E860BAD26311 ON organization_tag (tag_id)');
        $this->addSql('ALTER TABLE organization_tag ADD CONSTRAINT FK_904E86032C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE organization_tag ADD CONSTRAINT FK_904E860BAD26311 FOREIGN KEY (tag_id) REFERENCES tag (id) ON DELETE CASCADE');
        $this->addSql('DROP TABLE directory_entry');
    }
}
