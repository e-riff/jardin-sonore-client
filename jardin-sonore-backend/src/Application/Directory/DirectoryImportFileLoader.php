<?php

declare(strict_types=1);

namespace App\Application\Directory;

use JsonException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DirectoryImportFileLoader
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    /**
     * @return list<DirectoryEstablishmentImportItem>
     */
    public function load(string $fileArgument, int $offset = 0, ?int $limit = null): array
    {
        $filePath = $this->resolveImportFilePath($fileArgument);

        if (null === $filePath || !is_file($filePath) || !is_readable($filePath)) {
            throw new DirectoryImportFileException("JSON file not readable: {$fileArgument}");
        }

        try {
            $json = (string) file_get_contents($filePath);
            if (function_exists('json_validate') && !json_validate($json)) {
                throw new JsonException('Invalid JSON payload.');
            }

            /** @var array<string, mixed> $payload */
            $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new DirectoryImportFileException("Invalid JSON: {$jsonException->getMessage()}", 0, $jsonException);
        }

        $rows = $this->extractResultRows($payload);

        if ([] === $rows) {
            throw new DirectoryImportFileException('The JSON must contain a non-empty results or mainResults array.');
        }

        $items = array_map(
            static fn (array $row): DirectoryEstablishmentImportItem => DirectoryEstablishmentImportItem::fromArray($row),
            $rows,
        );

        if (0 < $offset || null !== $limit) {
            $items = array_slice($items, $offset, $limit);
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array<string, mixed>>
     */
    private function extractResultRows(array $payload): array
    {
        $rows = $payload['results'] ?? $payload['mainResults'] ?? null;

        if (!is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, 'is_array'));
    }

    private function resolveImportFilePath(string $fileArgument): ?string
    {
        $fileArgument = trim($fileArgument);

        if ('' === $fileArgument) {
            return null;
        }

        $candidatePaths = [
            $fileArgument,
            $this->projectDir . '/' . ltrim($fileArgument, '/'),
            $this->projectDir . '/data/' . ltrim($fileArgument, '/'),
            $this->projectDir . '/data/imports/' . ltrim($fileArgument, '/'),
        ];

        foreach (array_unique($candidatePaths) as $candidatePath) {
            if (is_file($candidatePath)) {
                return $candidatePath;
            }
        }

        return null;
    }
}
