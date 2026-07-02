<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use App\Domain\Model\Mailing\NewsletterRecommendation;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

final class NewsletterRecommendationFormModel
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 110)]
    public string $title = '';

    #[Assert\Length(max: 40)]
    public ?string $tag = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 320)]
    public string $text = '';

    #[Assert\Url]
    public ?string $url = null;

    #[Assert\Length(max: 60)]
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
        $formModel->tag = $recommendation->getTag();
        $formModel->text = $recommendation->getText();
        $formModel->url = $recommendation->getUrl();
        $formModel->linkLabel = $recommendation->getLinkLabel();
        $formModel->active = $recommendation->isActive();

        return $formModel;
    }
}
