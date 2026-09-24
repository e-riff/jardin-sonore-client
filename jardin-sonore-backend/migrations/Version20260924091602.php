<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260924091602 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove legacy UUID comments from geographic tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE department CHANGE uuid uuid BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE region CHANGE uuid uuid BINARY(16) NOT NULL');
        $this->addSql('ALTER TABLE municipality CHANGE uuid uuid BINARY(16) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE department CHANGE uuid uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)'");
        $this->addSql("ALTER TABLE region CHANGE uuid uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)'");
        $this->addSql("ALTER TABLE municipality CHANGE uuid uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)'");
    }
}
