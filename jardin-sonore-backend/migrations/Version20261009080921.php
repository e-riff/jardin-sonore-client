<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009080921 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Keep one durable contact email delivery per site request.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE commercial_contact_delivery (id INT AUTO_INCREMENT NOT NULL, payload_hash VARCHAR(64) NOT NULL, status VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, sent_at DATETIME DEFAULT NULL, attempts INT DEFAULT 0 NOT NULL, last_error LONGTEXT DEFAULT NULL, request_id INT NOT NULL, INDEX idx_commercial_contact_delivery_status (status), UNIQUE INDEX uniq_commercial_contact_delivery_request (request_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE commercial_contact_delivery ADD CONSTRAINT FK_F6749810427EB8A5 FOREIGN KEY (request_id) REFERENCES commercial_request (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commercial_contact_delivery DROP FOREIGN KEY FK_F6749810427EB8A5');
        $this->addSql('DROP TABLE commercial_contact_delivery');
    }
}
