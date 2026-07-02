<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use App\Domain\Model\Mailing\MailingRecommendation;
use Symfony\Component\Validator\Constraints as Assert;

final class MailingRecommendationFormModel
{
    #[Assert\Uuid]
    public ?string $uuid = null;

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

    public ?string $imagePath = null;

    public bool $active = true;

    public static function fromMailingRecommendation(MailingRecommendation $mailingRecommendation): self
    {
        $formModel = new self();
        $formModel->uuid = $mailingRecommendation->getUuid()->toRfc4122();
        $formModel->title = $mailingRecommendation->getTitle();
        $formModel->tag = $mailingRecommendation->getTag();
        $formModel->text = $mailingRecommendation->getText();
        $formModel->url = $mailingRecommendation->getUrl();
        $formModel->linkLabel = $mailingRecommendation->getLinkLabel();
        $formModel->imagePath = $mailingRecommendation->getImagePath();
        $formModel->active = $mailingRecommendation->isActive();

        return $formModel;
    }
}
