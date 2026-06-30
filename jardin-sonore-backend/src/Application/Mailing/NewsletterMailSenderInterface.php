<?php

declare(strict_types=1);

namespace App\Application\Mailing;

interface NewsletterMailSenderInterface
{
    public function sendTest(RenderedNewsletter $renderedNewsletter, string $recipientEmail): void;
}
