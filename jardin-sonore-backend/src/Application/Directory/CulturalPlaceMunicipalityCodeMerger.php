<?php

declare(strict_types=1);

namespace App\Application\Directory;

use RuntimeException;

final class CulturalPlaceMunicipalityCodeMerger
{
    /**
     * @param array<string, mixed>       $batch
     * @param list<array<string, mixed>> $sourceRows
     *
     * @return array<string, mixed>
     */
    public function merge(array $batch, array $sourceRows): array
    {
        $byId = [];
        foreach ($sourceRows as $sourceRow) {
            $item = $sourceRow['item'] ?? null;
            if (is_array($item) && is_string($item['id'] ?? null)) {
                $byId[$item['id']] = $item;
            }
        }
        if (!is_array($batch['rows'] ?? null)) {
            throw new RuntimeException('Fichier de travail invalide.');
        }
        foreach ($batch['rows'] as $index => $row) {
            if (!is_array($row) || !is_array($row['item'] ?? null) || !is_string($row['item']['id'] ?? null)) {
                throw new RuntimeException('Ligne de travail invalide.');
            }
            $item = $row['item'];
            $source = $byId[$item['id']] ?? null;
            if (!is_array($source) || !is_string($source['codeCommune'] ?? null) || '' === trim($source['codeCommune']) || ($item['commune'] ?? null) !== ($source['commune'] ?? null)) {
                throw new RuntimeException("Code INSEE introuvable ou commune modifiée pour {$item['id']} ; fichier inchangé.");
            }
            $batch['rows'][$index]['item']['codeCommune'] = $source['codeCommune'];
        }

        return $batch;
    }
}
