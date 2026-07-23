<?php

declare(strict_types=1);

namespace App\Domain\Model\ContentCatalog;

use App\Domain\Model\Behavior\IdentifiableInterface;
use App\Domain\Model\Behavior\IdentifiableTrait;
use App\Domain\Model\Behavior\UuidIdentifiableInterface;
use App\Domain\Model\Behavior\UuidIdentifiableTrait;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class InstrumentTag implements IdentifiableInterface, UuidIdentifiableInterface
{
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    public function __construct(
        private string $label,
        private string $color = '#64748b',
        ?Uuid $uuid = null,
        ?int $id = null,
    ) {
        $this->initializeId($id);
        $this->initializeUuid($uuid);
        $this->rename($label);
        $this->changeColor($color);
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function changeColor(string $color): void
    {
        $color = trim($color);
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            throw new InvalidArgumentException('Instrument tag color must be a hexadecimal color.');
        }

        $this->color = strtolower($color);
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function rename(string $label): void
    {
        $label = trim($label);

        if ('' === $label) {
            throw new InvalidArgumentException('Instrument tag label cannot be blank.');
        }

        $this->label = $label;
    }
}
