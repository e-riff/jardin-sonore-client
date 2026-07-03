<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

final class InvalidRecipientBatchFormModel
{
    public string $action = 'invalid_recipient';

    public string $emails = '';
}
