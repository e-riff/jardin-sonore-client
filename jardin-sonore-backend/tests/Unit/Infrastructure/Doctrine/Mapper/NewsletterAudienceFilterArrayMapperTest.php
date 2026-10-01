<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Doctrine\Mapper;

use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Infrastructure\Doctrine\Mapper\NewsletterAudienceFilterArrayMapper;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NewsletterAudienceFilterArrayMapperTest extends TestCase
{
    public function testHistoricalFiltersExcludeFreeSubscribers(): void
    {
        $newsletterAudienceFilter = (new NewsletterAudienceFilterArrayMapper())->toDomain([]);

        self::assertFalse($newsletterAudienceFilter->includesFreeSubscribers());
        self::assertFalse($newsletterAudienceFilter->hasActiveCriteria());
    }

    #[DataProvider('subscriptionOptions')]
    public function testFreeSubscriptionOptionSurvivesStorage(bool $includeFreeSubscribers): void
    {
        $mapper = new NewsletterAudienceFilterArrayMapper();
        $storedFilter = $mapper->toArray(new NewsletterAudienceFilter(includeFreeSubscribers: $includeFreeSubscribers));

        self::assertSame($includeFreeSubscribers, $storedFilter['includeFreeSubscribers']);
        $newsletterAudienceFilter = $mapper->toDomain($storedFilter);
        self::assertSame($includeFreeSubscribers, $newsletterAudienceFilter->includesFreeSubscribers());
        self::assertSame($includeFreeSubscribers, $newsletterAudienceFilter->hasActiveCriteria());
    }

    /** @return iterable<string, array{bool}> */
    public static function subscriptionOptions(): iterable
    {
        yield 'disabled' => [false];
        yield 'enabled' => [true];
    }

    #[DataProvider('invalidSubscriptionOptions')]
    public function testPresentNonBooleanOptionIsRejected(mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new NewsletterAudienceFilterArrayMapper())->toDomain(['includeFreeSubscribers' => $value]);
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidSubscriptionOptions(): iterable
    {
        yield 'null' => [null];
        yield 'integer' => [1];
        yield 'string' => ['false'];
        yield 'list' => [[]];
    }
}
