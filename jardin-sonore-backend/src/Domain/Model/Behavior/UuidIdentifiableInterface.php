<?php

declare(strict_types=1);

namespace App\Domain\Model\Behavior;

use Symfony\Component\Uid\Uuid;

interface UuidIdentifiableInterface
{
    public function getUuid(): Uuid;
}
