<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use Symfony\Component\Validator\Constraints as Assert;

final class MailingTestFormModel
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $recipientEmail = '';
}
