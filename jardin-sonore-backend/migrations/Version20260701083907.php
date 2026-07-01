<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260701083907 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align mailing delivery and messenger transport tables with the current Doctrine schema.';
    }

    public function up(Schema $schema): void
    {
        if ($this->tableExists('mailing_delivery_recipient')) {
            $this->addSql(<<<'SQL'
                ALTER TABLE
                  mailing_delivery_recipient
                CHANGE
                  campaign_uuid campaign_uuid VARCHAR(36) NOT NULL,
                CHANGE
                  queued_at queued_at DATETIME NOT NULL,
                CHANGE
                  dispatched_at dispatched_at DATETIME DEFAULT NULL,
                CHANGE
                  sent_at sent_at DATETIME DEFAULT NULL,
                CHANGE
                  failed_at failed_at DATETIME DEFAULT NULL,
                CHANGE
                  updated_at updated_at DATETIME NOT NULL
            SQL);
        }

        if (!$this->tableExists('messenger_messages')) {
            return;
        }

        if ($this->indexExists('messenger_messages', 'IDX_75EA56E016BA31DB')) {
            $this->addSql('DROP INDEX IDX_75EA56E016BA31DB ON messenger_messages');
        }

        if ($this->indexExists('messenger_messages', 'IDX_75EA56E0E3BD61CE')) {
            $this->addSql('DROP INDEX IDX_75EA56E0E3BD61CE ON messenger_messages');
        }

        if ($this->indexExists('messenger_messages', 'IDX_75EA56E0FB7336F0')) {
            $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0 ON messenger_messages');
        }

        $this->addSql('ALTER TABLE messenger_messages CHANGE created_at created_at DATETIME NOT NULL, CHANGE available_at available_at DATETIME NOT NULL, CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');

        if (!$this->indexExists('messenger_messages', 'IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750')) {
            $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->tableExists('mailing_delivery_recipient')) {
            $this->addSql(<<<'SQL'
                ALTER TABLE
                  mailing_delivery_recipient
                CHANGE
                  campaign_uuid campaign_uuid CHAR(36) NOT NULL,
                CHANGE
                  queued_at queued_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
                CHANGE
                  dispatched_at dispatched_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                CHANGE
                  sent_at sent_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                CHANGE
                  failed_at failed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
                CHANGE
                  updated_at updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'
            SQL);
        }

        if (!$this->tableExists('messenger_messages')) {
            return;
        }

        if ($this->indexExists('messenger_messages', 'IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750')) {
            $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages');
        }

        $this->addSql('ALTER TABLE messenger_messages CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE available_at available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE delivered_at delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');

        if (!$this->indexExists('messenger_messages', 'IDX_75EA56E016BA31DB')) {
            $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        }

        if (!$this->indexExists('messenger_messages', 'IDX_75EA56E0E3BD61CE')) {
            $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        }

        if (!$this->indexExists('messenger_messages', 'IDX_75EA56E0FB7336F0')) {
            $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        }
    }

    private function tableExists(string $tableName): bool
    {
        return in_array($tableName, $this->schemaManager()->listTableNames(), true);
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        foreach ($this->schemaManager()->listTableIndexes($tableName) as $existingIndexName => $index) {
            if ($existingIndexName === $indexName) {
                return true;
            }
        }

        return false;
    }

    private function schemaManager(): AbstractSchemaManager
    {
        return $this->connection->createSchemaManager();
    }
}
