<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Domain\Model\Session\SessionSummary;

interface SessionDocumentGeneratorInterface
{
    /** Returns a private temporary PDF for the caller to publish or discard. */
    public function generate(SessionSummary $sessionSummary): string;
}
