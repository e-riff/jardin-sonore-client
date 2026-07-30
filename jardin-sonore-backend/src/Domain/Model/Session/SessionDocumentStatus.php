<?php

declare(strict_types=1);

namespace App\Domain\Model\Session;

enum SessionDocumentStatus: string
{
    case PENDING = 'pending';
    case GENERATING = 'generating';
    case READY = 'ready';
    case FAILED = 'failed';
}
