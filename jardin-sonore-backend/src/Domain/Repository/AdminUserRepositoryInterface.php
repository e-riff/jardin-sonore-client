<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Administration\AdminUser;
use App\Domain\Model\ValueObject\EmailAddress;
use Symfony\Component\Uid\Uuid;

interface AdminUserRepositoryInterface
{
    public function findByUuid(Uuid $uuid): ?AdminUser;

    public function findByEmailAddress(EmailAddress $emailAddress): ?AdminUser;

    public function save(AdminUser $adminUser): void;
}
