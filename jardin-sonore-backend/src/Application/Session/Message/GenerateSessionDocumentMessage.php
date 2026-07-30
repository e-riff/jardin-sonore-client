<?php

declare(strict_types=1);

namespace App\Application\Session\Message;

final readonly class GenerateSessionDocumentMessage
{
    public function __construct(public string $sessionUuid)
    {
    }
}
