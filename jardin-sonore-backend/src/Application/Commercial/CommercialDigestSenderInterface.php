<?php

declare(strict_types=1);

namespace App\Application\Commercial;

interface CommercialDigestSenderInterface
{
    public function send(CommercialDigest $digest): void;
}
