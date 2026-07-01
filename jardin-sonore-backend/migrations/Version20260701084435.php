<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260701084435 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop obsolete single-column messenger transport indexes after switching to the combined index.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->tableExists('messenger_messages')) {
            return;
        }

        if ($this->indexExists('messenger_messages', 'IDX_75EA56E016BA31DB')) {
            $this->addSql('DROP INDEX IDX_75EA56E016BA31DB ON messenger_messages');
        }

        if ($this->indexExists('messenger_messages', 'IDX_75EA56E0FB7336F0')) {
            $this->addSql('DROP INDEX IDX_75EA56E0FB7336F0 ON messenger_messages');
        }

        if ($this->indexExists('messenger_messages', 'IDX_75EA56E0E3BD61CE')) {
            $this->addSql('DROP INDEX IDX_75EA56E0E3BD61CE ON messenger_messages');
        }
    }

    public function down(Schema $schema): void
    {
        if (!$this->tableExists('messenger_messages')) {
            return;
        }

        if (!$this->indexExists('messenger_messages', 'IDX_75EA56E016BA31DB')) {
            $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
        }

        if (!$this->indexExists('messenger_messages', 'IDX_75EA56E0FB7336F0')) {
            $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        }

        if (!$this->indexExists('messenger_messages', 'IDX_75EA56E0E3BD61CE')) {
            $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        }
    }

    private function tableExists(string $tableName): bool
    {
        return in_array($tableName, $this->schemaManager()->listTableNames(), true);
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        foreach ($this->schemaManager()->listTableIndexes($tableName) as $existingIndexName => $index) {
            if (0 === strcasecmp($existingIndexName, $indexName)) {
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
