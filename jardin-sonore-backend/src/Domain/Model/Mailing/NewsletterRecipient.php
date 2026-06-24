<?php

declare(strict_types=1);

namespace App\Domain\Model\Mailing;

use App\Domain\Model\ValueObject\EmailAddress;
use InvalidArgumentException;

final readonly class NewsletterRecipient
{
    public function __construct(
        private EmailAddress $emailAddress,
        private string $unsubscribeToken,
        private ?string $displayName = null,
    ) {
        $this->assertNotBlank($unsubscribeToken, 'Newsletter recipient unsubscribe token cannot be blank.');
    }

    public function getEmailAddress(): EmailAddress
    {
        return $this->emailAddress;
    }

    public function getUnsubscribeToken(): string
    {
        return $this->unsubscribeToken;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    private function assertNotBlank(string $value, string $message): void
    {
        if ('' === trim($value)) {
            throw new InvalidArgumentException($message);
        }
    }
}
