<?php

declare(strict_types=1);

namespace App\Application\Portal;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

final readonly class PortalListCriteria
{
    /** @param list<string> $themeUuids */
    public function __construct(
        public ?string $query,
        public ?string $organizationUuid,
        public array $themeUuids,
        public string $sort,
        public string $direction,
        public int $page,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $themeUuids = array_values(array_filter($request->query->all('theme'), static fn (mixed $themeUuid): bool => is_string($themeUuid) && Uuid::isValid($themeUuid)));
        $organizationUuid = $request->query->getString('organization');
        $sort = $request->query->getString('sort');
        $direction = $request->query->getString('direction');

        return new self(
            query: '' === trim($request->query->getString('q')) ? null : trim($request->query->getString('q')),
            organizationUuid: '' === trim($organizationUuid) ? null : trim($organizationUuid),
            themeUuids: array_values(array_unique($themeUuids)),
            sort: in_array($sort, ['date', 'title'], true) ? $sort : 'date',
            direction: in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc',
            page: max(1, $request->query->getInt('page', 1)),
        );
    }
}
