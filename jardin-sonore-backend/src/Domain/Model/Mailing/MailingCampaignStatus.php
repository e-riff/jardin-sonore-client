<?php

declare(strict_types=1);

namespace App\Domain\Model\Mailing;

enum MailingCampaignStatus: string
{
    case DRAFT = 'draft';
    case READY_FOR_TEST = 'ready_for_test';
    case TEST_SENT = 'test_sent';
    case DELIVERY_QUEUED = 'delivery_queued';
    case DELIVERY_SENDING = 'delivery_sending';
    case DELIVERY_STOPPED = 'delivery_stopped';
    case DELIVERY_SENT = 'delivery_sent';
    case DELIVERY_FAILED = 'delivery_failed';
}
