<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use Symfony\Component\Validator\Constraints as Assert;

final class SessionFamilyFormModel
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $name = '';

    /** @var list<string> */
    public array $themeUuids = [];
}
