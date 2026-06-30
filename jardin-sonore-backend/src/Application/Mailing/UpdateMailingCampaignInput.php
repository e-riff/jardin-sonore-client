<?php

declare(strict_types=1);

namespace App\Application\Mailing;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class UpdateMailingCampaignInput
{
    public function __construct(
        public string $internalTitle,
        public string $emailSubject,
        public string $publicTitle,
        public string $mainText,
        public ?string $subtitle,
        public ?string $callToActionLabel,
        public ?string $callToActionUrl,
        public ?UploadedFile $bannerImageFile,
        public string $templateKey,
    ) {
    }
}
