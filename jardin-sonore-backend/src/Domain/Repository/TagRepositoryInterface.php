<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\AddressBook\Tag;
use Symfony\Component\Uid\Uuid;

interface TagRepositoryInterface
{
    public function findByUuid(Uuid $uuid): ?Tag;

    public function findByLabel(string $label): ?Tag;

    public function save(Tag $tag): void;
}
