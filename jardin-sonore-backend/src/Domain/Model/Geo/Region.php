<?php

declare(strict_types=1);

namespace App\Domain\Model\Geo;

use App\Domain\Model\Behavior\IdentifiableInterface;
use App\Domain\Model\Behavior\IdentifiableTrait;
use App\Domain\Model\Behavior\UuidIdentifiableInterface;
use App\Domain\Model\Behavior\UuidIdentifiableTrait;
use App\Domain\Model\ValueObject\RegionCode;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class Region implements IdentifiableInterface, UuidIdentifiableInterface
{
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    public function __construct(
        private string $name,
        private RegionCode $code,
        ?Uuid $uuid = null,
        ?int $id = null,
    ) {
        $this->initializeId($id);
        $this->initializeUuid($uuid);
        $this->assertNameIsNotBlank($name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCode(): RegionCode
    {
        return $this->code;
    }

    public function rename(string $name): void
    {
        $this->assertNameIsNotBlank($name);
        $this->name = $name;
    }

    public function changeCode(RegionCode $code): void
    {
        $this->code = $code;
    }

    private function assertNameIsNotBlank(string $name): void
    {
        if ('' === trim($name)) {
            throw new InvalidArgumentException('Region name cannot be blank.');
        }
    }
}
