<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use App\Domain\Model\Mailing\NewsletterRecommendation;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

final class NewsletterRecommendationFormModel
{
    #[Assert\NotBlank]
    public string $title = '';

    #[Assert\NotBlank]
    public string $text = '';

    #[Assert\Url]
    public ?string $url = null;

    public ?string $linkLabel = null;

    #[Assert\Image(
        maxSize: '5M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
    )]
    public ?UploadedFile $imageFile = null;

    public bool $active = true;

    public static function fromNewsletterRecommendation(NewsletterRecommendation $recommendation): self
    {
        $formModel = new self();
        $formModel->title = $recommendation->getTitle();
        $formModel->text = $recommendation->getText();
        $formModel->url = $recommendation->getUrl();
        $formModel->linkLabel = $recommendation->getLinkLabel();
        $formModel->active = $recommendation->isActive();

        return $formModel;
    }
}
