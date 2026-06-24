<?php

declare(strict_types=1);

namespace App\Domain\Model\Mailing;

enum MailingCampaignStatus: string
{
    case DRAFT = 'draft';
    case READY_FOR_TEST = 'ready_for_test';
    case TEST_SENT = 'test_sent';
}
