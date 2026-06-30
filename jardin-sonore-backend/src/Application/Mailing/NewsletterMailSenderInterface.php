<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\NewsletterRecipient;

interface NewsletterMailSenderInterface
{
    public function sendTest(RenderedNewsletter $renderedNewsletter, string $recipientEmail): void;

    public function sendToRecipient(RenderedNewsletter $renderedNewsletter, NewsletterRecipient $newsletterRecipient): void;
}
