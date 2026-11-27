<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Repository\MailingAudienceMaskRepositoryInterface;
use DomainException;
use Symfony\Component\Uid\Uuid;

final readonly class DeleteMailingAudienceMask
{
    public function __construct(private MailingAudienceMaskRepositoryInterface $mailingAudienceMaskRepository)
    {
    }

    public function __invoke(Uuid $uuid): void
    {
        $mailingAudienceMask = $this->mailingAudienceMaskRepository->findByUuid($uuid);

        if (null !== $mailingAudienceMask) {
            if (!$mailingAudienceMask->canBeDeleted()) {
                throw new DomainException('The newsletter audience mask cannot be deleted.');
            }
            $this->mailingAudienceMaskRepository->remove($mailingAudienceMask);
        }
    }
}
