<?php

declare(strict_types=1);

namespace App\Application\Directory;

use RuntimeException;

final class CulturalPlaceWorkFile
{
    /** @return array<string, mixed> */
    public function load(string $path): array
    {
        $contents = @file_get_contents($path);
        if (false === $contents) {
            throw new RuntimeException('Impossible de lire le fichier de travail.');
        }
        $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !is_string($data['department'] ?? null) || !is_array($data['rows'] ?? null)) {
            throw new RuntimeException('Fichier de travail invalide.');
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    public function save(string $path, array $data): void
    {
        $temporaryPath = tempnam(dirname($path), '.cultural-');
        if (false === $temporaryPath) {
            throw new RuntimeException('Impossible de créer le fichier temporaire.');
        }
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            if (false === file_put_contents($temporaryPath, $json, LOCK_EX) || !rename($temporaryPath, $path)) {
                throw new RuntimeException('Impossible de sauvegarder le fichier de travail.');
            }
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
}
