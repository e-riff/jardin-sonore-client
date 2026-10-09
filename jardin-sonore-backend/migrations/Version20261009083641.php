<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009083641 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track sent commercial quotes and their signatures without storing documents.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE commercial_quote (id INT AUTO_INCREMENT NOT NULL, reference VARCHAR(64) NOT NULL, filename VARCHAR(255) NOT NULL, amount_cents INT NOT NULL, sent_on DATE NOT NULL, signed_on DATE DEFAULT NULL, project_id INT NOT NULL, replaces_id INT DEFAULT NULL, INDEX IDX_440FC9D59F193203 (replaces_id), INDEX idx_commercial_quote_project (project_id), UNIQUE INDEX uniq_commercial_quote_reference (reference), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE commercial_quote ADD CONSTRAINT FK_440FC9D5166D1F9C FOREIGN KEY (project_id) REFERENCES commercial_project (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE commercial_quote ADD CONSTRAINT FK_440FC9D59F193203 FOREIGN KEY (replaces_id) REFERENCES commercial_quote (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commercial_quote DROP FOREIGN KEY FK_440FC9D5166D1F9C');
        $this->addSql('ALTER TABLE commercial_quote DROP FOREIGN KEY FK_440FC9D59F193203');
        $this->addSql('DROP TABLE commercial_quote');
    }
}
