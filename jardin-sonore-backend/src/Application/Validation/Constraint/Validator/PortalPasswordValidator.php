<?php

declare(strict_types=1);

namespace App\Application\Validation\Constraint\Validator;

use App\Application\Validation\Constraint\PortalPassword;
use App\Domain\Model\Portal\PortalPasswordPolicy;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class PortalPasswordValidator extends ConstraintValidator
{
    public function __construct(private readonly PortalPasswordPolicy $portalPasswordPolicy)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof PortalPassword) {
            throw new UnexpectedTypeException($constraint, PortalPassword::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value) || !$this->portalPasswordPolicy->isValid($value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
