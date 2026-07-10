<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Repository\MailingAudienceMaskRepositoryInterface;

final readonly class ListMailingAudienceMasks
{
    public function __construct(private MailingAudienceMaskRepositoryInterface $mailingAudienceMaskRepository)
    {
    }

    /**
     * @return list<\App\Domain\Model\Mailing\MailingAudienceMask>
     */
    public function __invoke(): array
    {
        return $this->mailingAudienceMaskRepository->findAllOrderedByUpdatedAtDesc();
    }
}
