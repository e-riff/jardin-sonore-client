<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Model\Mailing\NewsletterRecommendation;
use Symfony\Component\Uid\Uuid;

interface NewsletterRecommendationRepositoryInterface
{
    public function findByUuid(Uuid $uuid): ?NewsletterRecommendation;

    /**
     * @return list<NewsletterRecommendation>
     */
    public function search(?string $query = null, bool $activeOnly = false): array;

    public function save(NewsletterRecommendation $newsletterRecommendation): void;

    public function delete(NewsletterRecommendation $newsletterRecommendation): void;

    /** @return list<string> */
    public function findTagSuggestions(): array;
}
