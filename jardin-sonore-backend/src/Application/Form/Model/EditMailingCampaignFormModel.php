<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use App\Domain\Model\Mailing\MailingCampaign;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class EditMailingCampaignFormModel
{
    #[Assert\NotBlank]
    public string $internalTitle = '';

    #[Assert\NotBlank]
    public string $emailSubject = '';

    #[Assert\NotBlank]
    public string $publicTitle = '';

    #[Assert\NotBlank]
    public string $mainText = '';

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
        $formModel->internalTitle = $mailingCampaign->getInternalTitle();
        $formModel->emailSubject = $mailingCampaign->getEmailSubject();
        $formModel->publicTitle = $mailingCampaign->getPublicTitle();
        $formModel->mainText = $mailingCampaign->getMainText();
        $formModel->subtitle = $mailingCampaign->getSubtitle();
        $formModel->callToActionLabel = $mailingCampaign->getCallToActionLabel();
        $formModel->callToActionUrl = $mailingCampaign->getCallToActionUrl();
        $formModel->bannerImagePath = $mailingCampaign->getBannerImagePath();
        $formModel->templateKey = $mailingCampaign->getTemplateKey();

        return $formModel;
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
