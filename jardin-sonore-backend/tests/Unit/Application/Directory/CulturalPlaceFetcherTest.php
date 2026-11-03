<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Directory;

use App\Application\Directory\CulturalPlaceFetcher;
use App\Application\Directory\CulturalPlaceNormalizer;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CulturalPlaceFetcherTest extends TestCase
{
    public function testFetchesEveryPageAndPrioritizesYouth(): void
    {
        $ordinary = ['id' => 'one', 'nom_du_lieu' => 'Bibliothèque', 'code_departement' => '42', 'sous_domaines' => '["Bibliothèque et médiathèque"]'];
        $youth = ['id' => 'two', 'nom_du_lieu' => 'Librairie', 'code_departement' => '42', 'sous_domaines' => '["Librairie"]', 'types' => '["Jeunesse"]'];
        $client = new MockHttpClient([
            new MockResponse(json_encode(['data' => [$ordinary], 'meta' => ['total' => 2]], JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode(['data' => [$youth], 'meta' => ['total' => 2]], JSON_THROW_ON_ERROR)),
        ]);

        $places = (new CulturalPlaceFetcher($client, new CulturalPlaceNormalizer()))->fetch('42', 1);
        self::assertSame(['two', 'one'], array_column(array_column($places, 'item'), 'id'));
    }

    public function testRejectsIncompletePage(): void
    {
        $client = new MockHttpClient([new MockResponse('{"data":[],"meta":{"total":1}}')]);
        $this->expectException(RuntimeException::class);
        (new CulturalPlaceFetcher($client, new CulturalPlaceNormalizer()))->fetch('42');
    }
}
