<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class NewsletterSubscriptionRequestInput
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 255)]
        public string $emailAddress,
    ) {
    }
}
