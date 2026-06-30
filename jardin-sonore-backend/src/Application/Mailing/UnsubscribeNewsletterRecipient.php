<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Repository\EmailContactRepositoryInterface;

final readonly class UnsubscribeNewsletterRecipient
{
    public function __construct(private EmailContactRepositoryInterface $emailContactRepository)
    {
    }

    public function __invoke(string $unsubscribeToken): bool
    {
        $unsubscribeToken = trim($unsubscribeToken);

        if ('' === $unsubscribeToken) {
            return false;
        }

        $emailContact = $this->emailContactRepository->findByUnsubscribeToken($unsubscribeToken);

        if (null === $emailContact) {
            return false;
        }

        if ($emailContact->isUnsubscribed()) {
            return true;
        }

        $emailContact->unsubscribe();
        $this->emailContactRepository->save($emailContact);

        return true;
    }
}
