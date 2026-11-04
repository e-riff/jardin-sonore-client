<?php

declare(strict_types=1);

namespace App\Application\Directory;

use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class CulturalPlaceFetcher
{
    private const string API_URL = 'https://tabular-api.data.gouv.fr/api/resources/4bd1a20e-4dfd-40ff-bd4d-c714f7085de8/data/';

    public function __construct(private HttpClientInterface $client, private CulturalPlaceNormalizer $normalizer)
    {
    }

    /**
     * @param list<string> $categories
     *
     * @return list<array{item: array<string, mixed>, youth: bool, sourceUpdatedAt: ?string}>
     */
    public function fetch(string $department, int $pageSize = 200, array $categories = []): array
    {
        $department = strtoupper(trim($department));
        if (1 !== preg_match('/^(?:\d{2}|2A|2B|97\d|98\d)$/', $department) || 1 > $pageSize || 200 < $pageSize) {
            throw new RuntimeException('Invalid department or page size.');
        }
        $items = [];
        $page = 1;
        do {
            $response = $this->client->request('GET', self::API_URL, ['query' => ['code_departement__exact' => $department, 'page_size' => $pageSize, 'page' => $page]])->toArray();
            $rows = $response['data'] ?? null;
            $total = $response['meta']['total'] ?? null;
            if (!is_array($rows) || !is_int($total) || ([] === $rows && ($page - 1) * $pageSize < $total)) {
                throw new RuntimeException('Invalid or incomplete cultural places response.');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new RuntimeException('Invalid cultural place.');
                }
                $item = $this->normalizer->normalize($row, $department);
                if (null !== $item && ([] === $categories || in_array($item['item']['type'], $categories, true))) {
                    $items[] = $item;
                }
            }
            ++$page;
        } while (($page - 1) * $pageSize < $total);
        usort($items, static fn (array $left, array $right): int => $right['youth'] <=> $left['youth']);

        return $items;
    }
}
