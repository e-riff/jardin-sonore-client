<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260710113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert mailing audience mask UUID columns from CHAR(36) to BINARY(16) to match Doctrine UUID mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE mailing_audience_mask ADD uuid_binary BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'");
        $this->addSql("UPDATE mailing_audience_mask SET uuid_binary = UNHEX(REPLACE(uuid, '-', ''))");
        $this->addSql('ALTER TABLE mailing_audience_mask DROP INDEX uniq_mailing_audience_mask_uuid');
        $this->addSql("ALTER TABLE mailing_audience_mask DROP COLUMN uuid, CHANGE uuid_binary uuid BINARY(16) NOT NULL COMMENT '(DC2Type:uuid)'");
        $this->addSql('ALTER TABLE mailing_audience_mask ADD UNIQUE INDEX uniq_mailing_audience_mask_uuid (uuid)');

        $this->addSql("ALTER TABLE mailing_campaign ADD applied_audience_mask_uuid_binary BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'");
        $this->addSql("UPDATE mailing_campaign SET applied_audience_mask_uuid_binary = CASE WHEN applied_audience_mask_uuid IS NULL THEN NULL ELSE UNHEX(REPLACE(applied_audience_mask_uuid, '-', '')) END");
        $this->addSql("ALTER TABLE mailing_campaign DROP COLUMN applied_audience_mask_uuid, CHANGE applied_audience_mask_uuid_binary applied_audience_mask_uuid BINARY(16) DEFAULT NULL COMMENT '(DC2Type:uuid)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE mailing_audience_mask ADD uuid_text CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)'");
        $this->addSql("UPDATE mailing_audience_mask SET uuid_text = LOWER(CONCAT(SUBSTR(HEX(uuid), 1, 8), '-', SUBSTR(HEX(uuid), 9, 4), '-', SUBSTR(HEX(uuid), 13, 4), '-', SUBSTR(HEX(uuid), 17, 4), '-', SUBSTR(HEX(uuid), 21, 12)))");
        $this->addSql('ALTER TABLE mailing_audience_mask DROP INDEX uniq_mailing_audience_mask_uuid');
        $this->addSql("ALTER TABLE mailing_audience_mask DROP COLUMN uuid, CHANGE uuid_text uuid CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'");
        $this->addSql('ALTER TABLE mailing_audience_mask ADD UNIQUE INDEX uniq_mailing_audience_mask_uuid (uuid)');

        $this->addSql("ALTER TABLE mailing_campaign ADD applied_audience_mask_uuid_text CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)'");
        $this->addSql("UPDATE mailing_campaign SET applied_audience_mask_uuid_text = CASE WHEN applied_audience_mask_uuid IS NULL THEN NULL ELSE LOWER(CONCAT(SUBSTR(HEX(applied_audience_mask_uuid), 1, 8), '-', SUBSTR(HEX(applied_audience_mask_uuid), 9, 4), '-', SUBSTR(HEX(applied_audience_mask_uuid), 13, 4), '-', SUBSTR(HEX(applied_audience_mask_uuid), 17, 4), '-', SUBSTR(HEX(applied_audience_mask_uuid), 21, 12))) END");
        $this->addSql("ALTER TABLE mailing_campaign DROP COLUMN applied_audience_mask_uuid, CHANGE applied_audience_mask_uuid_text applied_audience_mask_uuid CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)'");
    }
}
