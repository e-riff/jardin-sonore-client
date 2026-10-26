<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use Symfony\Component\Validator\Constraints as Assert;

final class TechnicalTaxonomyFormModel
{
    public const string COLOR_PATTERN = '/^(?:#[0-9a-fA-F]{6}|rgb\(\s*(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)\s*,\s*(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)\s*,\s*(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)\s*\))$/i';

    public string $label = '';

    #[Assert\Regex(pattern: self::COLOR_PATTERN, message: 'technical.taxonomy.error.invalid_color')]
    public string $color = '#64748b';
}
