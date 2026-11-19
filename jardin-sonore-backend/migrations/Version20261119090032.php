<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119090032 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Preserve legacy payment tracking while recording descriptions for simple sent invoices.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commercial_invoice ADD payment_tracking TINYINT DEFAULT 1 NOT NULL, ADD description LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commercial_invoice DROP payment_tracking, DROP description');
    }
}
