<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Repository\MailingCampaignRepositoryInterface;

final readonly class CreateMailingCampaign
{
    private const string DEFAULT_TEMPLATE_KEY = 'default';

    public function __construct(private MailingCampaignRepositoryInterface $mailingCampaignRepository)
    {
    }

    public function __invoke(CreateMailingCampaignInput $input): MailingCampaign
    {
        $mailingCampaign = new MailingCampaign(
            internalTitle: $input->internalTitle,
            emailSubject: $input->emailSubject,
            publicTitle: $input->publicTitle,
            mainText: $input->mainText,
            templateKey: self::DEFAULT_TEMPLATE_KEY,
            audienceFilter: NewsletterAudienceFilter::empty(),
        );

        $this->mailingCampaignRepository->save($mailingCampaign);

        return $mailingCampaign;
    }
}
