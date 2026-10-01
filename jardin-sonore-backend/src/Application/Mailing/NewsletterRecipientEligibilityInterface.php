<?php

declare(strict_types=1);

namespace App\Application\Mailing;

interface NewsletterRecipientEligibilityInterface
{
    public function isEligible(string $emailAddress): bool;
}
