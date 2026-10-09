<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009084558 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Configure the personal commercial digest and prevent duplicate delivery per local day.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE commercial_digest_delivery (id INT AUTO_INCREMENT NOT NULL, local_date DATE NOT NULL, created_at DATETIME NOT NULL, status VARCHAR(16) NOT NULL, attempts INT NOT NULL, sent_at DATETIME DEFAULT NULL, last_error VARCHAR(255) DEFAULT NULL, UNIQUE INDEX uniq_commercial_digest_local_date (local_date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE commercial_digest_settings (id INT NOT NULL, enabled TINYINT NOT NULL, send_time VARCHAR(5) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql("INSERT INTO commercial_digest_settings (id, enabled, send_time) VALUES (1, 1, '08:00')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE commercial_digest_delivery');
        $this->addSql('DROP TABLE commercial_digest_settings');
    }
}
