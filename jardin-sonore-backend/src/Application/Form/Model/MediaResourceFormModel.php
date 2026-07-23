<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use App\Application\Session\MediaResourceView;
use App\Domain\Model\Session\MediaResourceType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class MediaResourceFormModel
{
    public ?string $existingPrimaryUrl = null;

    public ?string $existingImageUrl = null;

    public MediaResourceType $type = MediaResourceType::SOUNDTRACK;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $title = '';

    #[Assert\Url]
    public ?string $primaryUrl = null;

    public ?UploadedFile $primaryFile = null;

    #[Assert\Length(max: 255)]
    public ?string $source = null;

    public ?string $description = null;

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
    /** @var list<string> */
    public array $themeUuids = [];

    public static function fromView(MediaResourceView $mediaResourceView): self
    {
        $formModel = new self();
        $formModel->existingPrimaryUrl = $mediaResourceView->primaryUrl;
        $formModel->existingImageUrl = $mediaResourceView->imageUrl;
        $formModel->type = $mediaResourceView->type;
        $formModel->title = $mediaResourceView->title;
        $formModel->primaryUrl = self::normalizeEditableUrl($mediaResourceView->primaryUrl);
        $formModel->source = $mediaResourceView->source;
        $formModel->description = $mediaResourceView->description;
        $formModel->secondaryUrl = $mediaResourceView->secondaryUrl;
        $formModel->imageUrl = self::normalizeEditableUrl($mediaResourceView->imageUrl);
        $formModel->active = $mediaResourceView->active;
        $formModel->themeUuids = array_column($mediaResourceView->themes, 'uuid');

        return $formModel;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $executionContext): void
    {
        if ('' === trim((string) $this->primaryUrl) && null === $this->primaryFile && null === self::normalizeStoredUrl($this->existingPrimaryUrl)) {
            $executionContext->buildViolation('Ajoutez un lien principal ou un fichier.')
                ->atPath('primaryUrl')
                ->addViolation();
        }

        if ('' !== trim((string) $this->primaryUrl) && null !== $this->primaryFile) {
            $executionContext->buildViolation('Choisissez soit un lien principal, soit un fichier principal.')
                ->atPath('primaryUrl')
                ->addViolation();
        }

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
