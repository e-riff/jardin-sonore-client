<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use App\Application\Session\SessionRecommendationView;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class SessionRecommendationFormModel
{
    public ?string $existingPrimaryUrl = null;

    public ?string $existingImageUrl = null;

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

    #[Assert\Url]
    public ?string $imageUrl = null;

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
        $formModel->existingImageUrl = $sessionRecommendationView->imageUrl;
        $formModel->title = $sessionRecommendationView->title;
        $formModel->text = $sessionRecommendationView->text;
        $formModel->notes = $sessionRecommendationView->notes;
        $formModel->primaryUrl = $sessionRecommendationView->primaryUrl;
        $formModel->secondaryUrl = $sessionRecommendationView->secondaryUrl;
        $formModel->imageUrl = self::normalizeEditableUrl($sessionRecommendationView->imageUrl);
        $formModel->active = $sessionRecommendationView->active;

        return $formModel;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $executionContext): void
    {
        if ('' !== trim((string) $this->imageUrl) && null !== $this->imageFile) {
            $executionContext->buildViolation('Choisissez soit un visuel distant, soit une image importée.')
                ->atPath('imageUrl')
                ->addViolation();
        }
    }

    private static function normalizeEditableUrl(?string $value): ?string
    {
        $normalizedValue = self::normalizeStoredUrl($value);

        if (null === $normalizedValue) {
            return null;
        }

        if (str_starts_with($normalizedValue, 'uploads/') || str_starts_with($normalizedValue, '/uploads/')) {
            return null;
        }

        return $normalizedValue;
    }

    private static function normalizeStoredUrl(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $normalizedValue = trim($value);

        if ('' === $normalizedValue) {
            return null;
        }

        return $normalizedValue;
    }
}
