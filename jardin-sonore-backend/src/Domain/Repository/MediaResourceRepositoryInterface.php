<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Session\MediaResource;
use App\Domain\Model\Session\MediaResourceType;
use Symfony\Component\Uid\Uuid;

interface MediaResourceRepositoryInterface
{
    public function findByUuid(Uuid $uuid): ?MediaResource;

    /** @return list<MediaResource> */
    public function search(?string $query = null, ?MediaResourceType $mediaResourceType = null, bool $activeOnly = false): array;

    public function save(MediaResource $mediaResource): void;

    public function delete(MediaResource $mediaResource): void;
}
