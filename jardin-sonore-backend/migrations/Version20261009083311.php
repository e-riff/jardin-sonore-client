<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009083311 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link commercial requests to the explicitly selected organization and person.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commercial_request ADD organization_id INT DEFAULT NULL, ADD person_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE commercial_request ADD CONSTRAINT FK_5906D42E32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE commercial_request ADD CONSTRAINT FK_5906D42E217BBB47 FOREIGN KEY (person_id) REFERENCES person (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX IDX_5906D42E32C8A3DE ON commercial_request (organization_id)');
        $this->addSql('CREATE INDEX IDX_5906D42E217BBB47 ON commercial_request (person_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commercial_request DROP FOREIGN KEY FK_5906D42E32C8A3DE');
        $this->addSql('ALTER TABLE commercial_request DROP FOREIGN KEY FK_5906D42E217BBB47');
        $this->addSql('DROP INDEX IDX_5906D42E32C8A3DE ON commercial_request');
        $this->addSql('DROP INDEX IDX_5906D42E217BBB47 ON commercial_request');
        $this->addSql('ALTER TABLE commercial_request DROP organization_id, DROP person_id');
    }
}
