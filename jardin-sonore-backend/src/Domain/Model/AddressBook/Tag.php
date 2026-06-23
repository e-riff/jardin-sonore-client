<?php

declare(strict_types=1);

namespace App\Domain\Model\AddressBook;

use App\Domain\Model\Behavior\IdentifiableInterface;
use App\Domain\Model\Behavior\IdentifiableTrait;
use App\Domain\Model\Behavior\UuidIdentifiableInterface;
use App\Domain\Model\Behavior\UuidIdentifiableTrait;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class Tag implements IdentifiableInterface, UuidIdentifiableInterface
{
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    public function __construct(
        private string $label,
        ?Uuid $uuid = null,
        ?int $id = null,
    ) {
        $this->initializeId($id);
        $this->initializeUuid($uuid);
        $this->assertLabelIsNotBlank($label);
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function rename(string $label): void
    {
        $this->assertLabelIsNotBlank($label);
        $this->label = $label;
    }

    private function assertLabelIsNotBlank(string $label): void
    {
        if ('' === trim($label)) {
            throw new InvalidArgumentException('Tag label cannot be blank.');
        }
    }
}
