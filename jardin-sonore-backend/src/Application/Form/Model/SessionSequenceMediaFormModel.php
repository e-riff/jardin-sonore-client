<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use App\Domain\Model\Session\MediaResourceType;
use App\Domain\Model\Session\SessionSequenceMedia;
use Symfony\Component\Validator\Constraints as Assert;

final class SessionSequenceMediaFormModel
{
    #[Assert\NotBlank]
    public string $label = '';

    public MediaResourceType $type = MediaResourceType::LINK;

    #[Assert\Url]
    public string $url = '';

    #[Assert\Url]
    public ?string $imageUrl = null;

    public bool $featured = false;

    public bool $displayOnSession = true;

    public static function fromDomain(SessionSequenceMedia $media): self
    {
        $model = new self();
        $model->label = $media->label;
        $model->type = $media->type;
        $model->url = $media->url;
        $model->imageUrl = $media->imageUrl;
        $model->featured = $media->featured;
        $model->displayOnSession = $media->isDisplayedOnSession();

        return $model;
    }

    public function toDomain(): SessionSequenceMedia
    {
        return new SessionSequenceMedia($this->label, $this->type, $this->url, $this->imageUrl, $this->featured, $this->displayOnSession);
    }
}
