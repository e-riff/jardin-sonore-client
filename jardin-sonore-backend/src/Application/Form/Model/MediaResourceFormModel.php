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

    public static function fromView(MediaResourceView $mediaResourceView): self
    {
        $formModel = new self();
        $formModel->type = $mediaResourceView->type;
        $formModel->title = $mediaResourceView->title;
        $formModel->primaryUrl = $mediaResourceView->primaryUrl;
        $formModel->source = $mediaResourceView->source;
        $formModel->description = $mediaResourceView->description;
        $formModel->secondaryUrl = $mediaResourceView->secondaryUrl;
        $formModel->imageUrl = $mediaResourceView->imageUrl;
        $formModel->active = $mediaResourceView->active;

        return $formModel;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $executionContext): void
    {
        if ('' === trim((string) $this->primaryUrl) && null === $this->primaryFile) {
            $executionContext->buildViolation('Ajoutez un lien principal ou un fichier.')
                ->atPath('primaryUrl')
                ->addViolation();
        }
    }
}
