<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Application\Storage\MailingBannerImageStorageInterface;
use App\Application\Storage\MailingIntroImageStorageInterface;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use Throwable;

final readonly class CreateMailingCampaign
{
    private const string DEFAULT_TEMPLATE_KEY = 'default';

    public function __construct(
        private MailingCampaignRepositoryInterface $mailingCampaignRepository,
        private MailingBannerImageStorageInterface $mailingBannerImageStorage,
        private MailingIntroBlockProcessor $mailingIntroBlockProcessor,
        private MailingIntroImageStorageInterface $mailingIntroImageStorage,
    ) {
    }

    public function __invoke(CreateMailingCampaignInput $input): MailingCampaign
    {
        $bannerImagePath = null === $input->bannerImageFile
            ? null
            : $this->mailingBannerImageStorage->store($input->bannerImageFile);

        $processedBlocks = null;
        try {
            $processedBlocks = null === $input->introBlocks ? null : $this->mailingIntroBlockProcessor->process($input->introBlocks);
            $mainText = null === $processedBlocks ? $input->mainText : MailingIntroBlockProcessor::plainText($processedBlocks->blocks);
            $mailingCampaign = new MailingCampaign(
                internalTitle: $input->internalTitle,
                emailSubject: $input->emailSubject,
                publicTitle: $input->publicTitle,
                mainText: $mainText,
                preheader: $input->preheader,
                introBlocks: $processedBlocks?->blocks,
                subtitle: $input->subtitle,
                callToActionLabel: $input->callToActionLabel,
                callToActionUrl: $input->callToActionUrl,
                bannerImagePath: $bannerImagePath,
                templateKey: self::DEFAULT_TEMPLATE_KEY,
                audienceFilter: NewsletterAudienceFilter::empty(),
            );

            $this->mailingCampaignRepository->save($mailingCampaign);
        } catch (Throwable $throwable) {
            foreach ($processedBlocks->newImagePaths ?? [] as $imagePath) {
                $this->mailingIntroImageStorage->delete($imagePath);
            }

            if (null !== $bannerImagePath) {
                $this->mailingBannerImageStorage->delete($bannerImagePath);
            }

            throw $throwable;
        }

        return $mailingCampaign;
    }
}
