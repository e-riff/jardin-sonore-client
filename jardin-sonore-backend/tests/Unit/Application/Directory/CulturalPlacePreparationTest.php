<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Directory;

use App\Application\Directory\CulturalPlaceFetcher;
use App\Application\Directory\CulturalPlaceNormalizer;
use App\Application\Directory\CulturalPlaceWorkFile;
use App\Infrastructure\Command\PrepareCulturalPlacesCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CulturalPlacePreparationTest extends TestCase
{
    public function testGuidedPreparationFiltersCategoriesAndProvidesNextCommand(): void
    {
        $path = sys_get_temp_dir() . '/cultural-guided-' . bin2hex(random_bytes(8)) . '.json';
        $base = ['code_departement' => '42', 'nom_du_lieu' => 'Lieu test'];
        $client = new MockHttpClient(new MockResponse(json_encode(['data' => [
            [...$base, 'id' => 'school', 'sous_domaines' => ['Conservatoire']],
            [...$base, 'id' => 'library', 'sous_domaines' => ['Bibliothèque et médiathèque']],
            [...$base, 'id' => 'bookshop', 'sous_domaines' => ['Librairie'], 'types' => ['Générale']],
        ], 'meta' => ['total' => 3]], JSON_THROW_ON_ERROR)));
        $workFile = new CulturalPlaceWorkFile();
        $tester = new CommandTester(new PrepareCulturalPlacesCommand(new CulturalPlaceFetcher($client, new CulturalPlaceNormalizer()), $workFile, sys_get_temp_dir()));
        $tester->setInputs(['42', '3', $path, 'yes']);
        try {
            self::assertSame(0, $tester->execute([]));
            $batch = $workFile->load($path);
            self::assertSame(['enseignement_artistique'], $batch['categories']);
            self::assertCount(1, $batch['rows']);
            self::assertSame('school', $batch['rows'][0]['item']['id']);
            self::assertNotEmpty($batch['preparedAt']);
            self::assertStringContainsString('app:directory:cultural-places-review', $tester->getDisplay());
            self::assertStringContainsString($path, $tester->getDisplay());
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testGeneralBookshopsAreExcludedAndNewCategoriesAreRecognized(): void
    {
        $normalizer = new CulturalPlaceNormalizer();
        $base = ['code_departement' => '42', 'nom_du_lieu' => 'Lieu test', 'id' => 'test'];
        self::assertNull($normalizer->normalize([...$base, 'sous_domaines' => ['Librairie'], 'types' => ['Générale']], '42'));
        foreach (['Conservatoire' => 'enseignement_artistique', 'École de musique' => 'enseignement_artistique', 'Centre culturel' => 'centre_culturel', 'Tiers-lieu' => 'tiers_lieu'] as $source => $expected) {
            self::assertSame($expected, $normalizer->normalize([...$base, 'sous_domaines' => [$source]], '42')['item']['type']);
        }
    }
}
