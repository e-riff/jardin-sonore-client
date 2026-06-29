<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateMailingCampaignFormModel
{
    #[Assert\NotBlank]
    public string $internalTitle = '';

    #[Assert\NotBlank]
    public string $emailSubject = '';

    #[Assert\NotBlank]
    public string $publicTitle = '';

    #[Assert\NotBlank]
    public string $mainText = '';
}
