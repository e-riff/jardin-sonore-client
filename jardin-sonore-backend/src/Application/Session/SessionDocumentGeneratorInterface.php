<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\SessionSummary;

interface SessionDocumentGeneratorInterface
{
    public function generate(SessionSummary $sessionSummary): string;
}
