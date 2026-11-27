<?php

declare(strict_types=1);

namespace App\Application\Mailing\Query;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class OutreachJournalFilter
{
    public function __construct(
        #[Assert\Uuid] public ?string $organizationUuid = null,
        #[Assert\Uuid] public ?string $campaignUuid = null,
        #[Assert\Length(max: 100)] public ?string $organizationType = null,
        #[Assert\Date] public ?string $since = null,
        #[Assert\Date] public ?string $until = null,
        #[Assert\Range(min: 1, max: 100000)] public int $page = 1,
    ) {
    }

    #[Assert\Callback]
    public function validateDates(ExecutionContextInterface $context): void
    {
        if ($this->since && $this->until && $this->since > $this->until) {
            $context->buildViolation('mailing.outreach.invalid_dates')->atPath('until')->addViolation();
        }
    }
}
