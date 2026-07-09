<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\MailingAudienceMask;
use App\Domain\Repository\MailingAudienceMaskRepositoryInterface;
use Symfony\Component\Uid\Uuid;

final readonly class GetMailingAudienceMask
{
    public function __construct(private MailingAudienceMaskRepositoryInterface $mailingAudienceMaskRepository)
    {
    }

    public function __invoke(Uuid $uuid): ?MailingAudienceMask
    {
        return $this->mailingAudienceMaskRepository->findByUuid($uuid);
    }
}
