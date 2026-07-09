<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Mailing\MailingAudienceMask;
use Symfony\Component\Uid\Uuid;

interface MailingAudienceMaskRepositoryInterface
{
    public function findByUuid(Uuid $uuid): ?MailingAudienceMask;

    /**
     * @return list<MailingAudienceMask>
     */
    public function findAllOrderedByUpdatedAtDesc(): array;

    public function save(MailingAudienceMask $mailingAudienceMask): void;
}
