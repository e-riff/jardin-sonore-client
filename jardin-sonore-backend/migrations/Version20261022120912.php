<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022120912 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renomme le chemin local du visuel des prolongements et retire la notion d’URL d’image externe.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE session_recommendation CHANGE image_url image_path VARCHAR(2048) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE session_recommendation CHANGE image_path image_url VARCHAR(2048) DEFAULT NULL');
    }
}
