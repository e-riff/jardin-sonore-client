<?php

declare(strict_types=1);

namespace App\Domain\Model\Administration;

use App\Domain\Model\Behavior\ActivableTrait;
use App\Domain\Model\Behavior\IdentifiableInterface;
use App\Domain\Model\Behavior\IdentifiableTrait;
use App\Domain\Model\Behavior\UuidIdentifiableInterface;
use App\Domain\Model\Behavior\UuidIdentifiableTrait;
use App\Domain\Model\ValueObject\EmailAddress;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class AdminUser implements IdentifiableInterface, UuidIdentifiableInterface
{
    use ActivableTrait;
    use IdentifiableTrait;
    use UuidIdentifiableTrait;

    public function __construct(
        private EmailAddress $emailAddress,
        private string $passwordHash,
        bool $active = true,
        ?Uuid $uuid = null,
        ?int $id = null,
    ) {
        $this->initializeId($id);
        $this->initializeUuid($uuid);
        $this->initializeActive($active);
        $this->assertPasswordHashIsNotBlank($passwordHash);
    }

    public function getEmailAddress(): EmailAddress
    {
        return $this->emailAddress;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function changeEmailAddress(EmailAddress $emailAddress): void
    {
        $this->emailAddress = $emailAddress;
    }

    public function changePasswordHash(string $passwordHash): void
    {
        $this->assertPasswordHashIsNotBlank($passwordHash);
        $this->passwordHash = $passwordHash;
    }

    private function assertPasswordHashIsNotBlank(string $passwordHash): void
    {
        if ('' === trim($passwordHash)) {
            throw new InvalidArgumentException('Admin user password hash cannot be blank.');
        }
    }
}
