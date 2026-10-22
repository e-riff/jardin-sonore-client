<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize organization types, rename RAM to RPE, and remove unused customer status data.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE organization SET type = 'rpe' WHERE type = 'ram'");
        $this->renameMailingAudienceType('ram', 'rpe');
        $this->addSql("UPDATE organization SET type = 'centre' WHERE type = 'centre_loisirs'");
        $this->renameMailingAudienceType('centre_loisirs', 'centre');
        $this->removeMailingAudienceCustomerStatuses();
        $this->addSql('DROP INDEX idx_directory_entry_customer_status ON directory_entry');
        $this->addSql('ALTER TABLE directory_entry DROP customer_status');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE organization SET type = 'ram' WHERE type = 'rpe'");
        $this->renameMailingAudienceType('rpe', 'ram');
        // The dropped statuses and audience filters cannot be restored after this migration.
        $this->addSql('ALTER TABLE directory_entry ADD customer_status VARCHAR(32) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_directory_entry_customer_status ON directory_entry (customer_status)');
        // Centre and leisure-center types were merged and cannot be split safely on rollback.
    }

    private function renameMailingAudienceType(string $oldType, string $newType): void
    {
        foreach (['mailing_campaign', 'mailing_audience_mask'] as $table) {
            foreach ($this->connection->iterateAssociative("SELECT id, audience_filter FROM {$table}") as $row) {
                $audienceFilter = json_decode((string) $row['audience_filter'], true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($audienceFilter) || !is_array($audienceFilter['organizationTypes'] ?? null)) {
                    continue;
                }

                $hasChanged = false;
                foreach ($audienceFilter['organizationTypes'] as &$organizationType) {
                    if ($oldType !== $organizationType) {
                        continue;
                    }

                    $organizationType = $newType;
                    $hasChanged = true;
                }
                unset($organizationType);

                if (!$hasChanged) {
                    continue;
                }

                $this->connection->update(
                    $table,
                    ['audience_filter' => json_encode($audienceFilter, JSON_THROW_ON_ERROR)],
                    ['id' => $row['id']],
                );
            }
        }
    }

    private function removeMailingAudienceCustomerStatuses(): void
    {
        foreach (['mailing_campaign', 'mailing_audience_mask'] as $table) {
            foreach ($this->connection->iterateAssociative("SELECT id, audience_filter FROM {$table}") as $row) {
                $audienceFilter = json_decode((string) $row['audience_filter'], true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($audienceFilter) || !array_key_exists('customerStatuses', $audienceFilter)) {
                    continue;
                }

                unset($audienceFilter['customerStatuses']);

                $this->connection->update(
                    $table,
                    ['audience_filter' => json_encode($audienceFilter, JSON_THROW_ON_ERROR)],
                    ['id' => $row['id']],
                );
            }
        }
    }
}
