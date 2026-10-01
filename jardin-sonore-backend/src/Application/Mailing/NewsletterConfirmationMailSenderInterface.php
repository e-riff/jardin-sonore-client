<?php

declare(strict_types=1);

namespace App\Application\Mailing;

interface NewsletterConfirmationMailSenderInterface
{
    public function sendConfirmation(string $emailAddress, string $rawToken): void;
}
