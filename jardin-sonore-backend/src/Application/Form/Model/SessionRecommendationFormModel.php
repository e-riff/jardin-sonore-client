<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use App\Application\Session\SessionRecommendationView;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

final class SessionRecommendationFormModel
{
    public ?string $existingPrimaryUrl = null;

    public ?string $existingImagePath = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $title = '';

    #[Assert\NotBlank]
    public string $text = '';

    public ?string $notes = null;

    #[Assert\Url]
    public ?string $primaryUrl = null;

    #[Assert\Url]
    public ?string $secondaryUrl = null;

    #[Assert\Image(
        maxSize: '5M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
    )]
    public ?UploadedFile $imageFile = null;

    public bool $active = true;

    public static function fromView(SessionRecommendationView $sessionRecommendationView): self
    {
        $formModel = new self();
        $formModel->existingPrimaryUrl = $sessionRecommendationView->primaryUrl;
        $formModel->existingImagePath = $sessionRecommendationView->imagePath;
        $formModel->title = $sessionRecommendationView->title;
        $formModel->text = $sessionRecommendationView->text;
        $formModel->notes = $sessionRecommendationView->notes;
        $formModel->primaryUrl = $sessionRecommendationView->primaryUrl;
        $formModel->secondaryUrl = $sessionRecommendationView->secondaryUrl;
        $formModel->active = $sessionRecommendationView->active;

        return $formModel;
    }
}
