<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\MailingCampaignContent;
use App\Domain\Model\Mailing\MailingCampaignType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class EditMailingCampaignFormModel
{
    use MailingIntroBlocksFormTrait;

    public ?MailingCampaignType $campaignType = null;

    public bool $usesStructuredContent = false;

    public bool $includesFreeSubscribers = false;

    /** @var array<string, MailingSectionFormModel> */
    #[Assert\Valid]
    public array $sections = [];

    #[Assert\NotBlank]
    public string $internalTitle = '';

    #[Assert\NotBlank]
    public string $emailSubject = '';

    #[Assert\NotBlank]
    public string $publicTitle = '';

    public string $mainText = '';

    #[Assert\Length(max: 255)]
    public ?string $preheader = null;

    public ?string $subtitle = null;

    public ?string $callToActionLabel = null;

    #[Assert\Url]
    public ?string $callToActionUrl = null;

    public ?string $bannerImagePath = null;

    public bool $removeBannerImage = false;

    #[Image(
        maxSize: '5M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        mimeTypesMessage: 'mailing.form.banner_image_invalid_type',
        maxSizeMessage: 'mailing.form.banner_image_invalid_size',
    )]
    public ?UploadedFile $bannerImageFile = null;

    #[Assert\NotBlank]
    public string $templateKey = 'default';

    public static function fromMailingCampaign(MailingCampaign $mailingCampaign): self
    {
        $formModel = new self();
        $formModel->campaignType = $mailingCampaign->getCampaignType();
        $formModel->includesFreeSubscribers = $mailingCampaign->getAudienceFilter()->includesFreeSubscribers();
        $structuredContent = $mailingCampaign->getStructuredContent();
        $formModel->usesStructuredContent = null !== $structuredContent;
        if (null !== $structuredContent) {
            $keys = MailingCampaignType::OUTREACH === $mailingCampaign->getCampaignType()
                ? MailingCampaignContent::OUTREACH_SECTIONS
                : MailingCampaignContent::NEWSLETTER_SECTIONS;
            foreach ($keys as $key) {
                $section = new MailingSectionFormModel();
                $section->enabled = $structuredContent->isSectionEnabled($key);
                $section->blocks = array_map(MailingIntroBlockFormModel::fromBlock(...), $structuredContent->getBlocks($key));
                $formModel->sections[$key] = $section;
            }
        }
        $formModel->internalTitle = $mailingCampaign->getInternalTitle();
        $formModel->emailSubject = $mailingCampaign->getEmailSubject();
        $formModel->publicTitle = $mailingCampaign->getPublicTitle();
        $formModel->mainText = $mailingCampaign->getMainText();
        $formModel->preheader = $mailingCampaign->getPreheader();
        $blocks = $mailingCampaign->getIntroBlocks();
        if (null === $blocks) {
            $mainText = str_replace(["\r\n", "\r"], "\n", trim($mailingCampaign->getMainText()));
            $blocks = '' === $mainText ? [] : [[
                'id' => 'legacy-introduction-0',
                'kind' => 'paragraph',
                'text' => str_replace("\n", '<br>', $mainText),
            ]];
        }

        $mergedBlocks = [];
        foreach ($blocks as $block) {
            $previousIndex = array_key_last($mergedBlocks);
            if ('paragraph' === ($block['kind'] ?? null)
                && null !== $previousIndex
                && 'paragraph' === ($mergedBlocks[$previousIndex]['kind'] ?? null)) {
                $mergedBlocks[$previousIndex]['text'] .= '<br><br>' . (string) ($block['text'] ?? '');
                continue;
            }

            $mergedBlocks[] = $block;
        }
        $formModel->introBlocks = array_map(MailingIntroBlockFormModel::fromBlock(...), $mergedBlocks);
        $imagePaths = [];
        foreach ($blocks as $block) {
            if (isset($block['id'], $block['imagePath']) && is_string($block['imagePath'])) {
                $imagePaths[(string) $block['id']] = $block['imagePath'];
            }
        }
        $formModel->setExistingIntroImagePaths($imagePaths);
        $formModel->subtitle = $mailingCampaign->getSubtitle();
        $formModel->callToActionLabel = $mailingCampaign->getCallToActionLabel();
        $formModel->callToActionUrl = $mailingCampaign->getCallToActionUrl();
        $formModel->bannerImagePath = $mailingCampaign->getBannerImagePath();
        $formModel->templateKey = $mailingCampaign->getTemplateKey();

        return $formModel;
    }

    #[Assert\Callback]
    public function validateCampaignType(ExecutionContextInterface $context): void
    {
        if (MailingCampaignType::OUTREACH === $this->campaignType && $this->includesFreeSubscribers) {
            $context->buildViolation('mailing.form.outreach_subscribers_invalid')->atPath('campaignType')->addViolation();
        }
    }

    #[Assert\Callback]
    public function validateCallToAction(ExecutionContextInterface $executionContext): void
    {
        $hasLabel = null !== $this->callToActionLabel && '' !== trim($this->callToActionLabel);
        $hasUrl = null !== $this->callToActionUrl && '' !== trim($this->callToActionUrl);

        if ($hasLabel === $hasUrl) {
            return;
        }

        $executionContext
            ->buildViolation('mailing.form.call_to_action_pair_help')
            ->atPath($hasLabel ? 'callToActionUrl' : 'callToActionLabel')
            ->addViolation();
    }
}
