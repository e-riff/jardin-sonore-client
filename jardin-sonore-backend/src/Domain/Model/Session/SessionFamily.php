<?php

declare(strict_types=1);

namespace App\Domain\Model\Session;

use App\Domain\Model\ContentCatalog\Theme;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class SessionFamily
{
    public string $name;
    public Uuid $uuid;

    /** @param list<Theme> $themes */
    public function __construct(string $name, public array $themes = [], ?Uuid $uuid = null)
    {
        $name = trim($name);
        if ('' === $name || 255 < mb_strlen($name)) {
            throw new InvalidArgumentException('Invalid session family name.');
        }
        $this->name = $name;
        $this->uuid = $uuid ?? Uuid::v4();
    }
}
