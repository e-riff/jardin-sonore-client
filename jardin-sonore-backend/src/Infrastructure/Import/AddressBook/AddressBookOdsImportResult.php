<?php

declare(strict_types=1);

namespace App\Infrastructure\Import\AddressBook;

final class AddressBookOdsImportResult
{
    public int $rowsRead = 0;

    public int $rowsIgnored = 0;

    public int $organizationsCreated = 0;

    public int $organizationsUpdated = 0;

    public int $addressesCreated = 0;

    public int $addressesUpdated = 0;

    public int $emailsCreated = 0;

    public int $emailsUpdated = 0;

    public int $emailsIgnored = 0;

    public int $phonesCreated = 0;

    public int $phonesIgnored = 0;

    /**
     * @var list<string>
     */
    private array $errors = [];

    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    public function hasErrors(): bool
    {
        return [] !== $this->errors;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
