<?php

declare(strict_types=1);

namespace App\Application\Mcp;

use App\Application\Mailing\AddNewsletterRecommendationToCampaign;
use App\Application\Mailing\CreateNewsletterRecommendation;
use App\Application\Mailing\CreateNewsletterRecommendationInput;
use App\Application\Mailing\GetMailingCampaign;
use App\Application\Mailing\GetNewsletterRecommendation;
use App\Application\Mailing\GetNewsletterRecommendationForEdit;
use App\Application\Mailing\NewsletterRecommendationView;
use App\Application\Mailing\RemoveMailingRecommendation;
use App\Application\Mailing\SearchNewsletterRecommendations;
use App\Application\Mailing\UpdateNewsletterRecommendation;
use App\Domain\Model\Mailing\MailingCampaign;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final readonly class McpMailingRecommendations
{
    public function __construct(
        private SearchNewsletterRecommendations $searchNewsletterRecommendations,
        private GetNewsletterRecommendationForEdit $getNewsletterRecommendationForEdit,
        private CreateNewsletterRecommendation $createNewsletterRecommendation,
        private UpdateNewsletterRecommendation $updateNewsletterRecommendation,
        private GetMailingCampaign $getMailingCampaign,
        private GetNewsletterRecommendation $getNewsletterRecommendation,
        private AddNewsletterRecommendationToCampaign $addNewsletterRecommendationToCampaign,
        private RemoveMailingRecommendation $removeMailingRecommendation,
        private MailingDraftManager $mailingDraftManager,
        private McpImagePayload $mcpImagePayload,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function search(?string $query, bool $activeOnly, int $limit): array
    {
        if (1 > $limit || 20 < $limit || (null !== $query && 160 < mb_strlen($query))) {
            throw new InvalidArgumentException('Invalid recommendation search.');
        }

        $results = [];
        foreach (($this->searchNewsletterRecommendations)($query, $activeOnly) as $view) {
            $results[] = $this->toArray($view);
            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /** @return array<string, mixed> */
    public function get(string $uuid): array
    {
        return $this->toArray($this->findView($uuid));
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        $this->assertKeys($input, ['title', 'tag', 'text', 'url', 'linkLabel', 'active']);
        $recommendation = ($this->createNewsletterRecommendation)(new CreateNewsletterRecommendationInput(
            title: $this->requiredText($input, 'title', 110),
            tag: $this->optionalText($input, 'tag', 40),
            text: $this->requiredText($input, 'text', 320),
            url: $this->optionalUrl($input, 'url'),
            linkLabel: $this->optionalText($input, 'linkLabel', 60),
            imageFile: null,
            active: $this->active($input, true),
        ));

        return $this->get($recommendation->getUuid()->toRfc4122());
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(string $uuid, array $input): array
    {
        $this->assertKeys($input, ['revision', 'title', 'tag', 'text', 'url', 'linkLabel', 'active']);
        $view = $this->findView($uuid);
        $this->assertRevision($view, $input['revision'] ?? null);
        ($this->updateNewsletterRecommendation)($view->uuid, new CreateNewsletterRecommendationInput(
            title: array_key_exists('title', $input) ? $this->requiredText($input, 'title', 110) : $view->title,
            tag: array_key_exists('tag', $input) ? $this->optionalText($input, 'tag', 40) : $view->tag,
            text: array_key_exists('text', $input) ? $this->requiredText($input, 'text', 320) : $view->text,
            url: array_key_exists('url', $input) ? $this->optionalUrl($input, 'url') : $view->url,
            linkLabel: array_key_exists('linkLabel', $input) ? $this->optionalText($input, 'linkLabel', 60) : $view->linkLabel,
            imageFile: null,
            active: $this->active($input, $view->active),
        ));

        return $this->get($uuid);
    }

    /** @return array<string, mixed> */
    public function setImage(string $uuid, string $revision, string $data, string $filename): array
    {
        $view = $this->findView($uuid);
        $this->assertRevision($view, $revision);

        return $this->mcpImagePayload->withUploadedFile($data, $filename, function (UploadedFile $uploadedFile) use ($view, $uuid): array {
            ($this->updateNewsletterRecommendation)($view->uuid, new CreateNewsletterRecommendationInput(
                title: $view->title,
                tag: $view->tag,
                text: $view->text,
                url: $view->url,
                linkLabel: $view->linkLabel,
                imageFile: $uploadedFile,
                active: $view->active,
            ));

            return $this->get($uuid);
        });
    }

    /** @return array<string, mixed> */
    public function attach(string $campaignUuid, string $revision, string $catalogUuid): array
    {
        $campaign = $this->findEditableCampaign($campaignUuid, $revision);
        $recommendation = ($this->getNewsletterRecommendation)($this->uuid($catalogUuid));
        if (null === $recommendation) {
            throw new InvalidArgumentException('Mailing catalog recommendation not found.');
        }
        ($this->addNewsletterRecommendationToCampaign)($campaign, $recommendation);

        return $this->mailingDraftManager->get($campaignUuid);
    }

    /** @return array<string, mixed> */
    public function detach(string $campaignUuid, string $revision, string $recommendationUuid): array
    {
        $campaign = $this->findEditableCampaign($campaignUuid, $revision);
        if (!($this->removeMailingRecommendation)($campaign, $this->uuid($recommendationUuid))) {
            throw new InvalidArgumentException('Mailing recommendation not found.');
        }

        return $this->mailingDraftManager->get($campaignUuid);
    }

    private function findEditableCampaign(string $campaignUuid, string $revision): MailingCampaign
    {
        $uuid = $this->uuid($campaignUuid);
        $campaign = ($this->getMailingCampaign)($uuid);
        if (null === $campaign || !$campaign->isEditable()) {
            throw new InvalidArgumentException('Mailing draft is missing or no longer editable.');
        }
        $current = $this->mailingDraftManager->get($campaignUuid);
        if (!hash_equals($current['revision'], $revision)) {
            throw new InvalidArgumentException('Mailing changed since it was read. Read it again.');
        }

        return $campaign;
    }

    private function findView(string $uuid): NewsletterRecommendationView
    {
        $view = ($this->getNewsletterRecommendationForEdit)($this->uuid($uuid));
        if (null === $view) {
            throw new InvalidArgumentException('Mailing catalog recommendation not found.');
        }

        return $view;
    }

    private function uuid(string $value): Uuid
    {
        if (!Uuid::isValid($value)) {
            throw new InvalidArgumentException('Invalid UUID.');
        }

        return Uuid::fromString($value);
    }

    /** @return array<string, mixed> */
    private function toArray(NewsletterRecommendationView $view): array
    {
        return [
            'uuid' => $view->uuid->toRfc4122(),
            'title' => $view->title,
            'tag' => $view->tag,
            'text' => $view->text,
            'url' => $view->url,
            'linkLabel' => $view->linkLabel,
            'imagePath' => $view->imagePath,
            'active' => $view->active,
            'revision' => $this->revision($view),
        ];
    }

    private function revision(NewsletterRecommendationView $view): string
    {
        return hash('sha256', implode("\0", [
            $view->uuid->toRfc4122(), $view->title, (string) $view->tag, $view->text,
            (string) $view->url, (string) $view->linkLabel, (string) $view->imagePath,
            $view->active ? '1' : '0', $view->updatedAt->format('U.u'),
        ]));
    }

    private function assertRevision(NewsletterRecommendationView $view, mixed $revision): void
    {
        if (!is_string($revision) || !hash_equals($this->revision($view), $revision)) {
            throw new InvalidArgumentException('Recommendation changed since it was read. Read it again.');
        }
    }

    /** @param array<string, mixed> $input
     * @param list<string> $allowed
     */
    private function assertKeys(array $input, array $allowed): void
    {
        if ([] !== array_diff(array_keys($input), $allowed)) {
            throw new InvalidArgumentException('Unexpected argument.');
        }
    }

    /** @param array<string, mixed> $input */
    private function requiredText(array $input, string $key, int $maxLength): string
    {
        $value = $input[$key] ?? null;
        if (!is_string($value) || '' === trim($value) || mb_strlen($value) > $maxLength) {
            throw new InvalidArgumentException("Invalid {$key}.");
        }

        return trim($value);
    }

    /** @param array<string, mixed> $input */
    private function optionalText(array $input, string $key, int $maxLength): ?string
    {
        $value = $input[$key] ?? null;
        if (null === $value || '' === $value) {
            return null;
        }
        if (!is_string($value) || mb_strlen($value) > $maxLength) {
            throw new InvalidArgumentException("Invalid {$key}.");
        }

        return trim($value);
    }

    /** @param array<string, mixed> $input */
    private function optionalUrl(array $input, string $key): ?string
    {
        $url = $this->optionalText($input, $key, 2048);
        if (null !== $url && (false === filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true))) {
            throw new InvalidArgumentException("Invalid {$key} URL.");
        }

        return $url;
    }

    /** @param array<string, mixed> $input */
    private function active(array $input, bool $default): bool
    {
        $active = array_key_exists('active', $input) ? $input['active'] : $default;
        if (!is_bool($active)) {
            throw new InvalidArgumentException('Invalid active state.');
        }

        return $active;
    }
}
