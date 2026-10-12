<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\MailingAudienceMask;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Repository\MailingAudienceMaskRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final readonly class UpdateMailingAudienceMask
{
    public function __construct(private MailingAudienceMaskRepositoryInterface $mailingAudienceMaskRepository)
    {
    }

    /** @param list<string> $materializedMunicipalityInseeCodes */
    public function __invoke(Uuid $uuid, string $name, NewsletterAudienceFilter $audienceFilter, array $materializedMunicipalityInseeCodes): MailingAudienceMask
    {
        $mailingAudienceMask = $this->mailingAudienceMaskRepository->findByUuid($uuid);

        if (null === $mailingAudienceMask || !$audienceFilter->hasActiveCriteria()) {
            throw new InvalidArgumentException('Mailing audience mask does not exist or has no active criteria.');
        }

        $mailingAudienceMask->update($name, $audienceFilter, $materializedMunicipalityInseeCodes);
        $this->mailingAudienceMaskRepository->save($mailingAudienceMask);

        return $mailingAudienceMask;
    }
}
