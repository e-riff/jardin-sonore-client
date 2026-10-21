<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Mime\Email;

final readonly class StandaloneDraftMimeBuilder
{
    public function build(string $recipient, string $subject, string $body, string $messageId, string $mailboxAddress, DateTimeImmutable $createdAt, ?string $htmlBody = null): string
    {
        if (false === filter_var($recipient, FILTER_VALIDATE_EMAIL)
            || false === filter_var($mailboxAddress, FILTER_VALIDATE_EMAIL)
            || '' === trim($subject)
            || 1 === preg_match('/[\x00-\x1F\x7F]/', $subject)
            || '' === trim($body)
            || 1 !== preg_match('/^<[^<>\s]{1,250}>$/D', $messageId)) {
            throw new InvalidArgumentException('Invalid draft headers or body.');
        }

        $email = (new Email())
            ->from($mailboxAddress)
            ->to($recipient)
            ->subject($subject)
            ->date($createdAt)
            ->text($body, 'utf-8');
        $email->getHeaders()->addIdHeader('Message-ID', trim($messageId, '<>'));
        if (null !== $htmlBody) {
            $email->html(DraftHtmlBody::sanitize($htmlBody), 'utf-8');
        }

        return $email->toString();
    }
}
