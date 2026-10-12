<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use App\Application\Storage\MailingBannerImageStorageInterface;
use App\Application\Storage\MailingIntroImageStorageInterface;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use Throwable;

final readonly class UpdateMailingCampaign
{
    public function __construct(
        private MailingCampaignRepositoryInterface $mailingCampaignRepository,
        private MailingBannerImageStorageInterface $mailingBannerImageStorage,
        private MailingIntroBlockProcessor $mailingIntroBlockProcessor,
        private MailingIntroImageStorageInterface $mailingIntroImageStorage,
    ) {
    }

    public function __invoke(MailingCampaign $mailingCampaign, UpdateMailingCampaignInput $input): void
    {
        $previousBannerImagePath = $mailingCampaign->getBannerImagePath();
        $previousIntroBlocks = $mailingCampaign->getIntroBlocks();
        $bannerImagePath = $input->removeBannerImage
            ? null
            : (null === $input->bannerImageFile
                ? $previousBannerImagePath
                : $this->mailingBannerImageStorage->store($input->bannerImageFile));

        $processedBlocks = null;
        try {
            $processedBlocks = null === $input->introBlocks
                ? null
                : $this->mailingIntroBlockProcessor->process($input->introBlocks, $previousIntroBlocks);
            $mainText = null === $processedBlocks ? $input->mainText : MailingIntroBlockProcessor::plainText($processedBlocks->blocks);
            $mailingCampaign->updateContent(
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
                templateKey: $input->templateKey,
            );

            $this->mailingCampaignRepository->save($mailingCampaign);
        } catch (Throwable $throwable) {
            foreach ($processedBlocks->newImagePaths ?? [] as $imagePath) {
                $this->mailingIntroImageStorage->delete($imagePath);
            }

            if (null !== $input->bannerImageFile && null !== $bannerImagePath) {
                $this->mailingBannerImageStorage->delete($bannerImagePath);
            }

            throw $throwable;
        }

        $obsoleteImagePaths = $processedBlocks->obsoleteImagePaths ?? array_values(array_filter(array_map(
            static fn (array $block): ?string => is_string($block['imagePath'] ?? null) ? $block['imagePath'] : null,
            $previousIntroBlocks ?? [],
        )));
        foreach ($obsoleteImagePaths as $imagePath) {
            $this->mailingIntroImageStorage->delete($imagePath);
        }

        if (null !== $previousBannerImagePath && $previousBannerImagePath !== $bannerImagePath) {
            $this->mailingBannerImageStorage->delete($previousBannerImagePath);
        }
    }
}
