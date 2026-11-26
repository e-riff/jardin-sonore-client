<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Application\Storage\MailingBannerImageStorageInterface;
use App\Application\Storage\MailingIntroImageStorageInterface;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\MailingCampaignContent;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use InvalidArgumentException;
use Throwable;

final readonly class CreateMailingCampaign
{
    public function __construct(
        private MailingCampaignRepositoryInterface $mailingCampaignRepository,
        private MailingBannerImageStorageInterface $mailingBannerImageStorage,
        private MailingIntroBlockProcessor $mailingIntroBlockProcessor,
        private MailingIntroImageStorageInterface $mailingIntroImageStorage,
    ) {
    }

    public function __invoke(CreateMailingCampaignInput $input): MailingCampaign
    {
        if (null === $input->campaignType) {
            throw new InvalidArgumentException('A campaign type is required.');
        }
        $bannerImagePath = null === $input->bannerImageFile
            ? null
            : $this->mailingBannerImageStorage->store($input->bannerImageFile);

        $processedBlocks = null;
        try {
            $processedBlocks = null === $input->introBlocks ? null : $this->mailingIntroBlockProcessor->process($input->introBlocks);
            $mainText = null === $processedBlocks ? $input->mainText : MailingIntroBlockProcessor::plainText($processedBlocks->blocks);
            $initialBlocks = $processedBlocks->blocks ?? ('' === trim($mainText) ? [] : [['id' => 'initial', 'kind' => 'paragraph', 'text' => $mainText]]);
            $structuredContent = MailingCampaignContent::fromInitialBlocks($input->campaignType, $initialBlocks);
            $mailingCampaign = new MailingCampaign(
                internalTitle: $input->internalTitle,
                emailSubject: $input->emailSubject,
                publicTitle: $input->publicTitle,
                mainText: $mainText,
                preheader: $input->preheader,
                introBlocks: null,
                campaignType: $input->campaignType,
                structuredContent: $structuredContent,
                subtitle: $input->subtitle,
                callToActionLabel: $input->callToActionLabel,
                callToActionUrl: $input->callToActionUrl,
                bannerImagePath: $bannerImagePath,
                templateKey: $input->campaignType->value,
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
