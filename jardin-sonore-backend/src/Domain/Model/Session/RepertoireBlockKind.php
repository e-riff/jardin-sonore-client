<?php

declare(strict_types=1);

namespace App\Domain\Model\Session;

enum RepertoireBlockKind: string
{
    case LINE = 'line';
    case BREAK = 'break';
    case SECTION = 'section';
}
