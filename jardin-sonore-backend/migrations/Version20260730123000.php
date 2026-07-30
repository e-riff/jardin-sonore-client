<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use JsonException;

final class Version20260730123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert legacy session sequence links into media snapshots.';
    }

    public function up(Schema $schema): void
    {
        foreach ($this->connection->fetchAllAssociative('SELECT id, sequences FROM session_summary') as $row) {
            try {
                $sequences = json_decode((string) $row['sequences'], true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }
            if (!is_array($sequences)) {
                continue;
            }

            $changed = false;
            foreach ($sequences as &$sequence) {
                if (!is_array($sequence) || array_key_exists('media', $sequence)) {
                    continue;
                }
                $primaryUrl = is_string($sequence['primaryUrl'] ?? null) ? trim($sequence['primaryUrl']) : '';
                $secondaryUrl = is_string($sequence['secondaryUrl'] ?? null) ? trim($sequence['secondaryUrl']) : '';
                $imageUrl = is_string($sequence['imageUrl'] ?? null) ? trim($sequence['imageUrl']) : '';
                $media = [];
                if ('' !== $primaryUrl) {
                    $media[] = ['label' => (string) ($sequence['title'] ?? $primaryUrl), 'type' => 'link', 'url' => $primaryUrl, 'imageUrl' => '' === $imageUrl ? null : $imageUrl, 'featured' => true, 'displayOnSession' => true];
                }
                if ('' !== $secondaryUrl) {
                    $media[] = ['label' => $secondaryUrl, 'type' => 'link', 'url' => $secondaryUrl, 'imageUrl' => null, 'featured' => false, 'displayOnSession' => true];
                }
                $sequence['media'] = $media;
                unset($sequence['primaryUrl'], $sequence['secondaryUrl'], $sequence['imageUrl']);
                $changed = true;
            }
            unset($sequence);
            if ($changed) {
                $this->addSql('UPDATE session_summary SET sequences = :sequences WHERE id = :id', ['sequences' => json_encode($sequences, JSON_THROW_ON_ERROR), 'id' => $row['id']]);
            }
        }
    }

    public function down(Schema $schema): void
    {
    }
}
