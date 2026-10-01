<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use Symfony\Component\Validator\Constraints as Assert;

final class NewsletterSubscriberFormModel
{
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 255)]
    public string $emailAddress = '';

    #[Assert\IsTrue(message: 'subscriber.consent_required')]
    public bool $consentAttested = false;
}
