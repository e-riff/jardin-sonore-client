<?php

declare(strict_types=1);

namespace App\Application\Mcp;

use App\Application\ContentCatalog\CreateInstrument;
use App\Application\ContentCatalog\FindInstrumentCatalogItems;
use App\Application\ContentCatalog\GetInstrumentForEdit;
use App\Application\ContentCatalog\InstrumentCatalogCriteria;
use App\Application\ContentCatalog\InstrumentEditView;
use App\Application\ContentCatalog\SaveInstrumentInput;
use App\Application\ContentCatalog\UpdateInstrument;
use App\Application\Session\CreateMediaResource;
use App\Application\Session\CreateRepertoireItem;
use App\Application\Session\CreateSessionRecommendation;
use App\Application\Session\GetMediaResourceForEdit;
use App\Application\Session\GetRepertoireItemForEdit;
use App\Application\Session\GetSessionRecommendationForEdit;
use App\Application\Session\MediaResourceView;
use App\Application\Session\RepertoireItemView;
use App\Application\Session\SaveMediaResourceInput;
use App\Application\Session\SaveRepertoireBlockInput;
use App\Application\Session\SaveRepertoireItemInput;
use App\Application\Session\SaveSessionRecommendationInput;
use App\Application\Session\SearchMediaResources;
use App\Application\Session\SearchRepertoireItems;
use App\Application\Session\SearchSessionRecommendations;
use App\Application\Session\SessionRecommendationView;
use App\Application\Session\UpdateMediaResource;
use App\Application\Session\UpdateRepertoireItem;
use App\Application\Session\UpdateSessionRecommendation;
use App\Domain\Model\Session\MediaResourceType;
use App\Domain\Model\Session\RepertoireBlockKind;
use App\Domain\Model\Session\RepertoireItemType;
use App\Domain\Repository\InstrumentTagRepositoryInterface;
use App\Domain\Repository\MediaResourceRepositoryInterface;
use App\Domain\Repository\ThemeRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final readonly class McpSessionCatalog
{
    private const array CATEGORIES = ['repertoire', 'instrument', 'media', 'recommendation'];

    public function __construct(
        private SearchRepertoireItems $searchRepertoireItems,
        private GetRepertoireItemForEdit $getRepertoireItemForEdit,
        private CreateRepertoireItem $createRepertoireItem,
        private UpdateRepertoireItem $updateRepertoireItem,
        private FindInstrumentCatalogItems $findInstrumentCatalogItems,
        private GetInstrumentForEdit $getInstrumentForEdit,
        private CreateInstrument $createInstrument,
        private UpdateInstrument $updateInstrument,
        private SearchMediaResources $searchMediaResources,
        private GetMediaResourceForEdit $getMediaResourceForEdit,
        private CreateMediaResource $createMediaResource,
        private UpdateMediaResource $updateMediaResource,
        private SearchSessionRecommendations $searchSessionRecommendations,
        private GetSessionRecommendationForEdit $getSessionRecommendationForEdit,
        private CreateSessionRecommendation $createSessionRecommendation,
        private UpdateSessionRecommendation $updateSessionRecommendation,
        private ThemeRepositoryInterface $themeRepository,
        private InstrumentTagRepositoryInterface $instrumentTagRepository,
        private MediaResourceRepositoryInterface $mediaResourceRepository,
        private McpImagePayload $mcpImagePayload,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function search(string $category, ?string $query, bool $activeOnly, int $limit): array
    {
        $this->assertCategory($category);
        if ((null !== $query && 160 < mb_strlen($query)) || 1 > $limit || 20 < $limit) {
            throw new InvalidArgumentException('Invalid catalog search.');
        }
        $query = null === $query ? null : trim($query);
        $items = match ($category) {
            'repertoire' => ($this->searchRepertoireItems)(query: $query, activeOnly: $activeOnly),
            'instrument' => array_values(array_filter(array_map(
                fn ($instrumentCatalogItem): ?InstrumentEditView => ($this->getInstrumentForEdit)($instrumentCatalogItem->uuid),
                ($this->findInstrumentCatalogItems)(new InstrumentCatalogCriteria(query: $query ?? '', activeFilter: $activeOnly ? 'active' : '', perPage: $limit))->items,
            ))),
            'media' => ($this->searchMediaResources)(query: $query, activeOnly: $activeOnly),
            'recommendation' => ($this->searchSessionRecommendations)(query: $query, activeOnly: $activeOnly),
            default => throw new InvalidArgumentException('Unknown catalog category.'),
        };

        return array_map(function (object $item) use ($category): array {
            $data = $this->view($category, $item);

            return array_intersect_key($data, array_flip(['uuid', 'title', 'name', 'type', 'source', 'description', 'text', 'imagePath', 'active', 'revision', 'url']));
        }, array_slice($items, 0, $limit));
    }

    /** @return array<string, mixed> */
    public function get(string $category, string $uuid): array
    {
        $this->assertCategory($category);
        $id = $this->uuid($uuid);
        $item = match ($category) {
            'repertoire' => ($this->getRepertoireItemForEdit)($id),
            'instrument' => ($this->getInstrumentForEdit)($id),
            'media' => ($this->getMediaResourceForEdit)($id),
            'recommendation' => ($this->getSessionRecommendationForEdit)($id),
            default => throw new InvalidArgumentException('Unknown catalog category.'),
        };
        if (null === $item) {
            throw new InvalidArgumentException('Catalog item not found.');
        }

        return $this->view($category, $item);
    }

    /** @return array<string, mixed> */
    public function terms(): array
    {
        return [
            'themes' => array_map(static fn ($theme): array => [
                'uuid' => $theme->getUuid()->toRfc4122(), 'label' => $theme->getLabel(), 'color' => $theme->getColor(),
            ], $this->themeRepository->findAllOrderedByLabel()),
            'instrumentTags' => array_map(static fn ($tag): array => [
                'uuid' => $tag->getUuid()->toRfc4122(), 'label' => $tag->getLabel(), 'color' => $tag->getColor(),
            ], $this->instrumentTagRepository->findAllOrderedByLabel()),
        ];
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function create(string $category, array $input): array
    {
        $this->assertCategory($category);
        $uuid = match ($category) {
            'repertoire' => ($this->createRepertoireItem)($this->repertoireInput($input))->getUuid(),
            'instrument' => ($this->createInstrument)($this->instrumentInput($input))->getUuid(),
            'media' => ($this->createMediaResource)($this->mediaInput($input))->getUuid(),
            'recommendation' => ($this->createSessionRecommendation)($this->recommendationInput($input))->getUuid(),
            default => throw new InvalidArgumentException('Unknown catalog category.'),
        };

        return $this->get($category, $uuid->toRfc4122());
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(string $category, string $uuid, array $input): array
    {
        $current = $this->get($category, $uuid);
        $revision = $input['revision'] ?? null;
        if (!is_string($revision) || !hash_equals($current['revision'], $revision)) {
            throw new InvalidArgumentException('Catalog item changed since it was read. Read it again.');
        }
        unset($input['revision']);
        if ('recommendation' === $category && array_key_exists('imagePath', $input)) {
            throw new InvalidArgumentException('Use the recommendation image upload tool to change its image.');
        }
        $values = array_replace($current, $input);
        unset($values['uuid'], $values['revision'], $values['url'], $values['updatedAt']);
        $id = $this->uuid($uuid);
        switch ($category) {
            case 'repertoire':
                ($this->updateRepertoireItem)($id, $this->repertoireInput($values));
                break;
            case 'instrument':
                ($this->updateInstrument)($id, $this->instrumentInput($values));
                break;
            case 'media':
                ($this->updateMediaResource)($id, $this->mediaInput($values, $current));
                break;
            case 'recommendation':
                ($this->updateSessionRecommendation)($id, $this->recommendationInput($values, true));
                break;
        }

        return $this->get($category, $uuid);
    }

    /** @return array<string, mixed> */
    public function setRecommendationImage(string $uuid, string $revision, string $data, string $filename): array
    {
        $current = $this->get('recommendation', $uuid);
        if (!hash_equals($current['revision'], $revision)) {
            throw new InvalidArgumentException('Recommendation changed since it was read. Read it again.');
        }

        return $this->mcpImagePayload->withUploadedFile($data, $filename, function (UploadedFile $uploadedFile) use ($uuid, $current): array {
            $current['imageFile'] = $uploadedFile;
            unset($current['uuid'], $current['revision'], $current['url'], $current['updatedAt']);
            ($this->updateSessionRecommendation)($this->uuid($uuid), $this->recommendationInput($current, true));

            return $this->get('recommendation', $uuid);
        });
    }

    private function assertCategory(string $category): void
    {
        if (!in_array($category, self::CATEGORIES, true)) {
            throw new InvalidArgumentException('Unknown catalog category.');
        }
    }

    private function uuid(string $value): Uuid
    {
        if (!Uuid::isValid($value)) {
            throw new InvalidArgumentException('Invalid catalog UUID.');
        }

        return Uuid::fromString($value);
    }

    /** @return array<string, mixed> */
    private function view(string $category, object $item): array
    {
        $data = match (true) {
            'repertoire' === $category && $item instanceof RepertoireItemView => $this->repertoireView($item),
            'instrument' === $category && $item instanceof InstrumentEditView => $this->instrumentView($item),
            'media' === $category && $item instanceof MediaResourceView => $this->mediaView($item),
            'recommendation' === $category && $item instanceof SessionRecommendationView => $this->recommendationView($item),
            default => throw new InvalidArgumentException('Invalid catalog item view.'),
        };
        $data['revision'] = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $data;
    }

    /** @return array<string, mixed> */
    private function repertoireView(RepertoireItemView $view): array
    {
        return [
            'uuid' => $view->uuid->toRfc4122(), 'type' => $view->type->value, 'title' => $view->title,
            'source' => $view->source, 'body' => $view->body,
            'contentBlocks' => array_map(static fn ($block): array => ['kind' => $block->kind->value, 'text' => $block->text, 'gesture' => $block->gesture], $view->contentBlocks),
            'generalInstructions' => $view->generalInstructions, 'notes' => $view->notes,
            'linkedMediaUuids' => $view->linkedMediaUuids, 'themeUuids' => array_column($view->themes, 'uuid'),
            'active' => $view->active, 'updatedAt' => $view->updatedAt->format(DATE_ATOM),
            'url' => $this->urlGenerator->generate('repertoire_edit', ['uuid' => $view->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }

    /** @return array<string, mixed> */
    private function instrumentView(InstrumentEditView $view): array
    {
        return [
            'uuid' => $view->uuid->toRfc4122(), 'name' => $view->name, 'tuning' => $view->tuning,
            'quantity' => $view->quantity, 'notes' => $view->notes, 'tagUuids' => $view->tagUuids,
            'active' => $view->active,
            'url' => $this->urlGenerator->generate('instrument_edit', ['uuid' => $view->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }

    /** @return array<string, mixed> */
    private function mediaView(MediaResourceView $view): array
    {
        return [
            'uuid' => $view->uuid->toRfc4122(), 'type' => $view->type->value, 'title' => $view->title,
            'primaryUrl' => $view->primaryUrl, 'source' => $view->source, 'description' => $view->description,
            'secondaryUrl' => $view->secondaryUrl, 'imageUrl' => $view->imageUrl,
            'themeUuids' => array_column($view->themes, 'uuid'), 'active' => $view->active,
            'updatedAt' => $view->updatedAt->format(DATE_ATOM),
            'url' => $this->urlGenerator->generate('media_resource_edit', ['uuid' => $view->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }

    /** @return array<string, mixed> */
    private function recommendationView(SessionRecommendationView $view): array
    {
        return [
            'uuid' => $view->uuid->toRfc4122(), 'title' => $view->title, 'text' => $view->text,
            'notes' => $view->notes, 'primaryUrl' => $view->primaryUrl, 'secondaryUrl' => $view->secondaryUrl,
            'imagePath' => $view->imagePath, 'active' => $view->active,
            'updatedAt' => $view->updatedAt->format(DATE_ATOM),
            'url' => $this->urlGenerator->generate('session_recommendation_edit', ['uuid' => $view->uuid], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }

    /** @param array<string, mixed> $input */
    private function repertoireInput(array $input): SaveRepertoireItemInput
    {
        $this->assertKeys($input, ['type', 'title', 'source', 'body', 'contentBlocks', 'generalInstructions', 'notes', 'linkedMediaUuids', 'themeUuids', 'active']);
        $type = $input['type'] ?? null;
        if (!is_string($type) || null === ($repertoireItemType = RepertoireItemType::tryFrom($type))) {
            throw new InvalidArgumentException('Invalid repertoire type.');
        }
        $blocks = $input['contentBlocks'] ?? [];
        if (!is_array($blocks) || !array_is_list($blocks) || 50 < count($blocks)) {
            throw new InvalidArgumentException('Invalid repertoire blocks.');
        }
        $contentBlocks = [];
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                throw new InvalidArgumentException('Invalid repertoire block.');
            }
            $this->assertKeys($block, ['kind', 'text', 'gesture']);
            $kind = $block['kind'] ?? null;
            if (!is_string($kind) || null === ($repertoireBlockKind = RepertoireBlockKind::tryFrom($kind))) {
                throw new InvalidArgumentException('Invalid repertoire block kind.');
            }
            $contentBlocks[] = new SaveRepertoireBlockInput($repertoireBlockKind, $this->optionalText($block, 'text', 10000), $this->optionalText($block, 'gesture', 10000));
        }
        $linkedMediaUuids = $this->uuidList($input, 'linkedMediaUuids');
        foreach ($linkedMediaUuids as $linkedMediaUuid) {
            if (null === $this->mediaResourceRepository->findByUuid($this->uuid($linkedMediaUuid))) {
                throw new InvalidArgumentException('Linked media resource not found.');
            }
        }

        return new SaveRepertoireItemInput(
            type: $repertoireItemType,
            title: $this->requiredText($input, 'title', 255),
            source: $this->optionalText($input, 'source', 255),
            body: $this->textAllowingEmpty($input, 'body', 20000),
            contentBlocks: $contentBlocks,
            generalInstructions: $this->optionalText($input, 'generalInstructions', 20000),
            notes: $this->optionalText($input, 'notes', 20000),
            linkedMediaUuids: $linkedMediaUuids,
            themeUuids: $this->themeUuids($input),
            active: $this->active($input),
        );
    }

    /** @param array<string, mixed> $input */
    private function instrumentInput(array $input): SaveInstrumentInput
    {
        $this->assertKeys($input, ['name', 'tuning', 'quantity', 'notes', 'tagUuids', 'active']);
        $quantity = $input['quantity'] ?? null;
        if (null !== $quantity && (!is_int($quantity) || 0 > $quantity)) {
            throw new InvalidArgumentException('Invalid instrument quantity.');
        }
        $tagUuids = $this->uuidList($input, 'tagUuids');
        foreach ($tagUuids as $tagUuid) {
            if (null === $this->instrumentTagRepository->findByUuid($this->uuid($tagUuid))) {
                throw new InvalidArgumentException('Instrument tag not found.');
            }
        }

        return new SaveInstrumentInput(
            name: $this->requiredText($input, 'name', 255),
            tuning: $this->optionalText($input, 'tuning', 255),
            quantity: $quantity,
            notes: $this->optionalText($input, 'notes', 20000),
            tagUuids: $tagUuids,
            active: $this->active($input),
        );
    }

    /** @param array<string, mixed> $input
     * @param array<string, mixed>|null $current
     */
    private function mediaInput(array $input, ?array $current = null): SaveMediaResourceInput
    {
        $this->assertKeys($input, ['type', 'title', 'primaryUrl', 'source', 'description', 'secondaryUrl', 'imageUrl', 'themeUuids', 'active']);
        $type = $input['type'] ?? null;
        if (!is_string($type) || null === ($mediaResourceType = MediaResourceType::tryFrom($type))) {
            throw new InvalidArgumentException('Invalid media resource type.');
        }
        $primaryUrl = $this->requiredText($input, 'primaryUrl', 2048);
        if (!$this->validUrl($primaryUrl) && (null === $current || $primaryUrl !== $current['primaryUrl'])) {
            throw new InvalidArgumentException('Invalid primary media URL.');
        }
        $secondaryUrl = $this->optionalText($input, 'secondaryUrl', 2048);
        $imageUrl = $this->optionalText($input, 'imageUrl', 2048);
        foreach (['secondaryUrl' => $secondaryUrl, 'imageUrl' => $imageUrl] as $field => $url) {
            if (null !== $url && !$this->validUrl($url) && (null === $current || $url !== $current[$field])) {
                throw new InvalidArgumentException('Invalid media URL.');
            }
        }

        return new SaveMediaResourceInput(
            type: $mediaResourceType,
            title: $this->requiredText($input, 'title', 255),
            primaryUrl: $primaryUrl,
            primaryFile: null,
            source: $this->optionalText($input, 'source', 255),
            description: $this->optionalText($input, 'description', 20000),
            secondaryUrl: $secondaryUrl,
            imageUrl: $imageUrl,
            imageFile: null,
            themeUuids: $this->themeUuids($input),
            active: $this->active($input),
        );
    }

    /** @param array<string, mixed> $input */
    private function recommendationInput(array $input, bool $existing = false): SaveSessionRecommendationInput
    {
        $this->assertKeys($input, ['title', 'text', 'notes', 'primaryUrl', 'secondaryUrl', 'imagePath', 'imageFile', 'clearImage', 'active']);
        if (!$existing && (array_key_exists('imagePath', $input) || array_key_exists('clearImage', $input))) {
            throw new InvalidArgumentException('Use the image upload tool after creating the recommendation.');
        }
        $imageFile = $input['imageFile'] ?? null;
        if (null !== $imageFile && !$imageFile instanceof UploadedFile) {
            throw new InvalidArgumentException('Invalid image file.');
        }
        $clearImage = $input['clearImage'] ?? false;
        if (!is_bool($clearImage) || ($clearImage && null !== $imageFile)) {
            throw new InvalidArgumentException('Invalid image clearing request.');
        }
        $imagePath = $clearImage ? null : ($input['imagePath'] ?? null);
        if (null !== $imagePath && (!is_string($imagePath) || !str_starts_with($imagePath, 'uploads/session/recommendations/'))) {
            throw new InvalidArgumentException('Invalid local recommendation image path.');
        }
        $primaryUrl = $this->optionalText($input, 'primaryUrl', 2048);
        $secondaryUrl = $this->optionalText($input, 'secondaryUrl', 2048);
        foreach ([$primaryUrl, $secondaryUrl] as $url) {
            if (null !== $url && !$this->validUrl($url)) {
                throw new InvalidArgumentException('Invalid recommendation link.');
            }
        }

        return new SaveSessionRecommendationInput(
            title: $this->requiredText($input, 'title', 255),
            text: $this->requiredText($input, 'text', 20000),
            notes: $this->optionalText($input, 'notes', 20000),
            primaryUrl: $primaryUrl,
            secondaryUrl: $secondaryUrl,
            imagePath: $imagePath,
            imageFile: $imageFile,
            active: $this->active($input),
        );
    }

    /** @param array<string, mixed> $input
     * @return list<string>
     */
    private function themeUuids(array $input): array
    {
        $themeUuids = $this->uuidList($input, 'themeUuids');
        foreach ($themeUuids as $themeUuid) {
            if (null === $this->themeRepository->findByUuid($this->uuid($themeUuid))) {
                throw new InvalidArgumentException('Theme not found.');
            }
        }

        return $themeUuids;
    }

    /** @param array<string, mixed> $input
     * @return list<string>
     */
    private function uuidList(array $input, string $key): array
    {
        $values = $input[$key] ?? [];
        if (!is_array($values) || !array_is_list($values) || 50 < count($values)) {
            throw new InvalidArgumentException("Invalid {$key} list.");
        }
        foreach ($values as $value) {
            if (!is_string($value) || !Uuid::isValid($value)) {
                throw new InvalidArgumentException("Invalid {$key} UUID.");
            }
        }

        return array_values(array_unique($values));
    }

    /** @param array<string, mixed> $input
     * @param list<string> $allowed
     */
    private function assertKeys(array $input, array $allowed): void
    {
        if ([] !== array_diff(array_keys($input), $allowed)) {
            throw new InvalidArgumentException('Unexpected catalog field.');
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
    private function textAllowingEmpty(array $input, string $key, int $maxLength): string
    {
        $value = $input[$key] ?? '';
        if (!is_string($value) || mb_strlen($value) > $maxLength) {
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
    private function active(array $input): bool
    {
        $active = array_key_exists('active', $input) ? $input['active'] : true;
        if (!is_bool($active)) {
            throw new InvalidArgumentException('Invalid active state.');
        }

        return $active;
    }

    private function validUrl(string $value): bool
    {
        return false !== filter_var($value, FILTER_VALIDATE_URL) && in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true);
    }
}
