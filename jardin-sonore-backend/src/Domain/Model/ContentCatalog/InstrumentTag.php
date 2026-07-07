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
        ?Uuid $uuid = null,
        ?int $id = null,
    ) {
        $this->initializeId($id);
        $this->initializeUuid($uuid);
        $this->rename($label);
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
