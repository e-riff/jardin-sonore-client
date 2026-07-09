<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\MailingAudienceMask;
use App\Domain\Repository\MailingAudienceMaskRepositoryInterface;
use InvalidArgumentException;

final readonly class CreateMailingAudienceMask
{
    public function __construct(private MailingAudienceMaskRepositoryInterface $mailingAudienceMaskRepository)
    {
    }

    public function __invoke(CreateMailingAudienceMaskInput $input): MailingAudienceMask
    {
        if (!$input->audienceFilter->hasActiveCriteria()) {
            throw new InvalidArgumentException('Mailing audience mask cannot be created from an empty audience.');
        }

        $mailingAudienceMask = new MailingAudienceMask(
            name: $input->name,
            audienceFilter: $input->audienceFilter,
            materializedMunicipalityInseeCodes: $input->materializedMunicipalityInseeCodes,
        );

        $this->mailingAudienceMaskRepository->save($mailingAudienceMask);

        return $mailingAudienceMask;
    }
}
