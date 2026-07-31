<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Form\Model;

use App\Application\Form\Model\SessionSummaryFormModel;
use PHPUnit\Framework\TestCase;

final class SessionSummaryFormModelTest extends TestCase
{
    public function testRecommendationOrderCanBeMissingFromTheSubmittedForm(): void
    {
        $sessionSummaryFormModel = new SessionSummaryFormModel();
        $sessionSummaryFormModel->recommendationUuids = ['first'];
        $sessionSummaryFormModel->recommendationOrder = null;

        self::assertSame(['first'], $sessionSummaryFormModel->orderedRecommendationUuids());
    }

    public function testItUsesTheSubmittedRecommendationOrderWithoutTrustingUnknownValues(): void
    {
        $sessionSummaryFormModel = new SessionSummaryFormModel();
        $sessionSummaryFormModel->recommendationUuids = ['first', 'second', 'third'];
        $sessionSummaryFormModel->recommendationOrder = 'third,unknown,first';

        self::assertSame(['third', 'first', 'second'], $sessionSummaryFormModel->orderedRecommendationUuids());
    }
}
