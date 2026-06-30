<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Application\Storage\MailingBannerImageStorageInterface;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Repository\MailingCampaignRepositoryInterface;

final readonly class UpdateMailingCampaign
{
    public function __construct(
        private MailingCampaignRepositoryInterface $mailingCampaignRepository,
        private MailingBannerImageStorageInterface $mailingBannerImageStorage,
    ) {
    }

    public function __invoke(MailingCampaign $mailingCampaign, UpdateMailingCampaignInput $input): void
    {
        $bannerImagePath = $input->removeBannerImage
            ? null
            : (null === $input->bannerImageFile
                ? $mailingCampaign->getBannerImagePath()
                : $this->mailingBannerImageStorage->store($input->bannerImageFile));

        $mailingCampaign->updateContent(
            internalTitle: $input->internalTitle,
            emailSubject: $input->emailSubject,
            publicTitle: $input->publicTitle,
            mainText: $input->mainText,
            subtitle: $input->subtitle,
            callToActionLabel: $input->callToActionLabel,
            callToActionUrl: $input->callToActionUrl,
            bannerImagePath: $bannerImagePath,
            templateKey: $input->templateKey,
        );

        $this->mailingCampaignRepository->save($mailingCampaign);
    }
}
