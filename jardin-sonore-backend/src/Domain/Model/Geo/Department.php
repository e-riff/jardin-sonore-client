<?php

declare(strict_types=1);

namespace App\Domain\Model\Geo;

use App\Domain\Model\Behavior\IdentifiableInterface;
use App\Domain\Model\Behavior\IdentifiableTrait;
use App\Domain\Model\Behavior\UuidIdentifiableInterface;
use App\Domain\Model\Behavior\UuidIdentifiableTrait;
use App\Domain\Model\ValueObject\DepartmentCode;
use Symfony\Component\Uid\Uuid;

final class Department implements IdentifiableInterface, UuidIdentifiableInterface
{
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    public function __construct(
        private string $name,
        private DepartmentCode $code,
        private Region $region,
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

    public function getCode(): DepartmentCode
    {
        return $this->code;
    }

    public function getRegion(): Region
    {
        return $this->region;
    }

    public function rename(string $name): void
    {
        $this->assertNameIsNotBlank($name);
        $this->name = $name;
    }

    public function changeCode(DepartmentCode $code): void
    {
        $this->code = $code;
    }

    public function attachToRegion(Region $region): void
    {
        $this->region = $region;
    }

    private function assertNameIsNotBlank(string $name): void
    {
        if ('' === trim($name)) {
            throw new \InvalidArgumentException('Department name cannot be blank.');
        }
    }
}
