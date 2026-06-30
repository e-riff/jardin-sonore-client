<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Application\Storage\MailingBannerImageStorageInterface;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Repository\MailingCampaignRepositoryInterface;

final readonly class CreateMailingCampaign
{
    private const string DEFAULT_TEMPLATE_KEY = 'default';

    public function __construct(
        private MailingCampaignRepositoryInterface $mailingCampaignRepository,
        private MailingBannerImageStorageInterface $mailingBannerImageStorage,
    ) {
    }

    public function __invoke(CreateMailingCampaignInput $input): MailingCampaign
    {
        $bannerImagePath = null === $input->bannerImageFile
            ? null
            : $this->mailingBannerImageStorage->store($input->bannerImageFile);

        $mailingCampaign = new MailingCampaign(
            internalTitle: $input->internalTitle,
            emailSubject: $input->emailSubject,
            publicTitle: $input->publicTitle,
            mainText: $input->mainText,
            subtitle: $input->subtitle,
            callToActionLabel: $input->callToActionLabel,
            callToActionUrl: $input->callToActionUrl,
            bannerImagePath: $bannerImagePath,
            templateKey: self::DEFAULT_TEMPLATE_KEY,
            audienceFilter: NewsletterAudienceFilter::empty(),
        );

        $this->mailingCampaignRepository->save($mailingCampaign);

        return $mailingCampaign;
    }
}
