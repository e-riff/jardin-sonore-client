<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\AddressBook;

final readonly class OdsSpreadsheetReader
{
    private const string TABLE_NAMESPACE = 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';

    private const string TEXT_NAMESPACE = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';

    /**
     * @param list<string> $sheetNames
     *
     * @return array<string, list<list<string>>>
     */
    public function readSheets(string $filePath, array $sheetNames): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new \RuntimeException(sprintf('ODS file "%s" cannot be read.', $filePath));
        }

        $zipArchive = new \ZipArchive();

        if (true !== $zipArchive->open($filePath)) {
            throw new \RuntimeException(sprintf('ODS file "%s" cannot be opened.', $filePath));
        }

        $content = $zipArchive->getFromName('content.xml');
        $zipArchive->close();

        if (!is_string($content)) {
            throw new \RuntimeException(sprintf('ODS file "%s" does not contain content.xml.', $filePath));
        }

        $spreadsheet = simplexml_load_string($content);

        if (!$spreadsheet instanceof \SimpleXMLElement) {
            throw new \RuntimeException(sprintf('ODS file "%s" contains invalid XML.', $filePath));
        }

        $spreadsheet->registerXPathNamespace('table', self::TABLE_NAMESPACE);
        $tables = $spreadsheet->xpath('//table:table');

        if (!is_array($tables)) {
            return [];
        }

        $expectedSheetNames = array_flip($sheetNames);
        $sheets = [];

        foreach ($tables as $table) {
            $tableAttributes = $table->attributes(self::TABLE_NAMESPACE);
            $sheetName = (string) ($tableAttributes['name'] ?? '');

            if (!array_key_exists($sheetName, $expectedSheetNames)) {
                continue;
            }

            $sheets[$sheetName] = $this->readRows($table);
        }

        return $sheets;
    }

    /**
     * @return list<list<string>>
     */
    private function readRows(\SimpleXMLElement $table): array
    {
        $rows = [];

        foreach ($table->children(self::TABLE_NAMESPACE)->{'table-row'} as $tableRow) {
            $rowRepeatCount = $this->readRepeatCount($tableRow, 'number-rows-repeated');
            $row = $this->readCells($tableRow);

            if ([] === $row) {
                continue;
            }

            for ($index = 0; $index < $rowRepeatCount; ++$index) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function readCells(\SimpleXMLElement $tableRow): array
    {
        $row = [];

        foreach ($tableRow->children(self::TABLE_NAMESPACE) as $cell) {
            if ('table-cell' !== $cell->getName()) {
                continue;
            }

            $value = $this->readCellValue($cell);
            $repeatCount = $this->readRepeatCount($cell, 'number-columns-repeated');

            if ('' === $value && $repeatCount > 50) {
                $repeatCount = 1;
            }

            for ($index = 0; $index < $repeatCount; ++$index) {
                $row[] = $value;
            }
        }

        while ([] !== $row && '' === $row[array_key_last($row)]) {
            array_pop($row);
        }

        return array_any($row, static fn (string $value): bool => '' !== $value) ? $row : [];
    }

    private function readCellValue(\SimpleXMLElement $cell): string
    {
        $parts = [];

        foreach ($cell->children(self::TEXT_NAMESPACE)->p as $paragraph) {
            $value = trim((string) $paragraph);

            if ('' !== $value) {
                $parts[] = $value;
            }
        }

        return implode("\n", $parts);
    }

    private function readRepeatCount(\SimpleXMLElement $element, string $attributeName): int
    {
        $attributes = $element->attributes(self::TABLE_NAMESPACE);
        $repeatCount = (int) ($attributes[$attributeName] ?? 1);

        return max(1, $repeatCount);
    }
}
