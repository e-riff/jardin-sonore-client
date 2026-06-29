<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\NewsletterRecipient;
use InvalidArgumentException;

final readonly class NewsletterAudienceResolution
{
    /**
     * @param list<NewsletterRecipient> $recipients
     */
    public function __construct(
        private int $total,
        private array $recipients,
    ) {
        if (0 > $total || count($recipients) > $total) {
            throw new InvalidArgumentException('Newsletter audience resolution total is inconsistent.');
        }
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * @return list<NewsletterRecipient>
     */
    public function getRecipients(): array
    {
        return $this->recipients;
    }

    public function isTruncated(): bool
    {
        return count($this->recipients) < $this->total;
    }
}
