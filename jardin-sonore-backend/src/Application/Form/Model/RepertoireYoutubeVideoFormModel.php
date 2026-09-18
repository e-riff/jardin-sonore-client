<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use Symfony\Component\Validator\Constraints as Assert;

final class RepertoireYoutubeVideoFormModel
{
    #[Assert\NotBlank]
    #[Assert\Url]
    public ?string $youtubeUrl = null;
}
