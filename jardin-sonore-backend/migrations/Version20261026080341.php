<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026080341 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Freeze legacy mailing tag audiences, then remove directory tags';
    }

    public function up(Schema $schema): void
    {
        $directoryEntryUuidsByTagUuid = $this->fetchDirectoryEntryUuidsByTagUuid();
        $this->freezeTagFilters('mailing_campaign', $directoryEntryUuidsByTagUuid);
        $this->freezeTagFilters('mailing_audience_mask', $directoryEntryUuidsByTagUuid);

        $this->addSql('ALTER TABLE directory_entry_tag DROP FOREIGN KEY `FK_DIRECTORY_ENTRY_TAG_ENTRY`');
        $this->addSql('ALTER TABLE directory_entry_tag DROP FOREIGN KEY `FK_DIRECTORY_ENTRY_TAG_TAG`');
        $this->addSql('DROP TABLE directory_entry_tag');
        $this->addSql('DROP TABLE tag');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Directory tags and their labels cannot be reconstructed from the frozen mailing audiences.');
    }

    /**
     * @return array<string, list<string>>
     */
    private function fetchDirectoryEntryUuidsByTagUuid(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(tag.uuid)) AS tag_uuid, LOWER(HEX(directory_entry.uuid)) AS directory_entry_uuid
            FROM directory_entry_tag
            INNER JOIN tag ON tag.id = directory_entry_tag.tag_id
            INNER JOIN directory_entry ON directory_entry.id = directory_entry_tag.directory_entry_id',
        );
        $directoryEntryUuidsByTagUuid = [];

        foreach ($rows as $row) {
            $tagUuid = $this->uuidFromHex((string) $row['tag_uuid']);
            $directoryEntryUuid = $this->uuidFromHex((string) $row['directory_entry_uuid']);
            $directoryEntryUuidsByTagUuid[$tagUuid][] = $directoryEntryUuid;
        }

        return $directoryEntryUuidsByTagUuid;
    }

    /**
     * @param array<string, list<string>> $directoryEntryUuidsByTagUuid
     */
    private function freezeTagFilters(string $table, array $directoryEntryUuidsByTagUuid): void
    {
        $rows = $this->connection->fetchAllAssociative("SELECT id, audience_filter FROM {$table}");

        foreach ($rows as $row) {
            $audienceFilter = json_decode((string) $row['audience_filter'], true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($audienceFilter)) {
                continue;
            }

            $tagUuids = $audienceFilter['tagUuids'] ?? [];
            if (!is_array($tagUuids) || [] === $tagUuids) {
                continue;
            }

            $directoryEntryUuids = $audienceFilter['directoryEntryUuids'] ?? [];
            if (!is_array($directoryEntryUuids)) {
                $directoryEntryUuids = [];
            }

            foreach ($tagUuids as $tagUuid) {
                if (!is_string($tagUuid)) {
                    continue;
                }

                foreach ($directoryEntryUuidsByTagUuid[strtolower($tagUuid)] ?? [] as $directoryEntryUuid) {
                    $directoryEntryUuids[] = $directoryEntryUuid;
                }
            }

            $audienceFilter['directoryEntryUuids'] = array_values(array_unique($directoryEntryUuids));
            unset($audienceFilter['tagUuids']);
            $this->connection->update($table, [
                'audience_filter' => json_encode($audienceFilter, JSON_THROW_ON_ERROR),
            ], ['id' => $row['id']]);
        }
    }

    private function uuidFromHex(string $uuidHex): string
    {
        $uuidHex = strtolower($uuidHex);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($uuidHex, 0, 8),
            substr($uuidHex, 8, 4),
            substr($uuidHex, 12, 4),
            substr($uuidHex, 16, 4),
            substr($uuidHex, 20),
        );
    }
}
