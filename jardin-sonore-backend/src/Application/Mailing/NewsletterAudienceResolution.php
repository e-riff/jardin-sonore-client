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
        private int $directoryCount = 0,
        private int $subscriberCount = 0,
        private int $overlapCount = 0,
    ) {
        if (0 > $total || count($recipients) > $total) {
            throw new InvalidArgumentException('Newsletter audience resolution total is inconsistent.');
        }
    }

    public function getDirectoryCount(): int
    {
        return $this->directoryCount;
    }

    public function getSubscriberCount(): int
    {
        return $this->subscriberCount;
    }

    public function getOverlapCount(): int
    {
        return $this->overlapCount;
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
