<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class CreateMailingCampaignFormModel
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

    #[Image(
        maxSize: '5M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        mimeTypesMessage: 'mailing.form.banner_image_invalid_type',
        maxSizeMessage: 'mailing.form.banner_image_invalid_size',
    )]
    public ?UploadedFile $bannerImageFile = null;

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
