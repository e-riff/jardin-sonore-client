<?php

declare(strict_types=1);

namespace App\Application\Validation\Constraint;

use App\Application\Validation\Constraint\Validator\PortalPasswordValidator;
use Attribute;
use Symfony\Component\Validator\Constraint;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class PortalPassword extends Constraint
{
    public string $message = 'portal.password.invalid';

    public function validatedBy(): string
    {
        return PortalPasswordValidator::class;
    }
}
