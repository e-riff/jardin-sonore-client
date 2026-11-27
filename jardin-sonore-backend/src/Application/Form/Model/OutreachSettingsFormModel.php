<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use Symfony\Component\Validator\Constraints as Assert;

final class OutreachSettingsFormModel
{
    public function __construct(
        #[Assert\Range(min: 0, max: 520)]
        public int $weeks = 6,
    ) {
    }
}
