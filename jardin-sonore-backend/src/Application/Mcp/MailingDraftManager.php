<?php

declare(strict_types=1);

namespace App\Application\Mcp;

use App\Application\Mailing\MailingIntroBlockProcessor;
use App\Application\Mailing\RecommendationText;
use App\Domain\Model\Mailing\MailingAudienceMask;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\MailingCampaignContent;
use App\Domain\Model\Mailing\MailingCampaignType;
use App\Domain\Model\Mailing\MailingRecommendation;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Repository\MailingAudienceMaskRepositoryInterface;
use App\Domain\Repository\MailingCampaignRepositoryInterface;
use App\Infrastructure\Mailing\NewsletterRichTextFormatter;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\Uid\Uuid;

final readonly class MailingDraftManager
{
    private const string REQUEST_NAMESPACE = '16614f9d-e7c9-4535-9396-ea6b87e772c2';

    public function __construct(
        private MailingCampaignRepositoryInterface $campaignRepository,
        private MailingAudienceMaskRepositoryInterface $maskRepository,
        private NewsletterRichTextFormatter $richTextFormatter,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function search(?string $query, int $limit): array
    {
        $results = [];
        foreach ($this->campaignRepository->findAllOrderedByCreatedAtDesc() as $campaign) {
            if (null !== $query && !str_contains(mb_strtolower($campaign->getInternalTitle() . ' ' . $campaign->getEmailSubject() . ' ' . $campaign->getPublicTitle()), mb_strtolower($query))) {
                continue;
            }

            $results[] = [
                'campaignUuid' => (string) $campaign->getUuid(),
                'campaignType' => $campaign->getCampaignType()?->value,
                'structuredContent' => $campaign->getStructuredContent()?->toArray(),
                'internalTitle' => $campaign->getInternalTitle(),
                'emailSubject' => $campaign->getEmailSubject(),
                'status' => $campaign->getStatus()->value,
                'editable' => $campaign->isEditable(),
                'createdAt' => $campaign->getCreatedAt()->format(DATE_ATOM),
            ];
            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /** @return array<string, mixed> */
    public function get(string $campaignUuid): array
    {
        return $this->view($this->findCampaign($campaignUuid));
    }

    /** @return list<array{maskUuid: string, name: string}> */
    public function masks(): array
    {
        $masks = [];
        foreach ($this->maskRepository->findAllOrderedByUpdatedAtDesc() as $mask) {
            $masks[] = ['maskUuid' => (string) $mask->getUuid(), 'name' => $mask->getName()];
            if (20 <= count($masks)) {
                break;
            }
        }

        return $masks;
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function create(array $input): array
    {
        $this->assertKeys($input, ['campaignType', 'structuredContent', 'requestId', 'internalTitle', 'emailSubject', 'publicTitle', 'mainText', 'introBlocks', 'preheader', 'subtitle', 'callToActionLabel', 'callToActionUrl', 'audienceMaskUuid']);
        $requestId = $this->requiredText($input, 'requestId', 100);
        $uuid = Uuid::v5(Uuid::fromString(self::REQUEST_NAMESPACE), $requestId);
        $existing = $this->campaignRepository->findByUuid($uuid);
        if (null !== $existing) {
            return $this->view($existing);
        }

        $campaignType = MailingCampaignType::tryFrom($this->requiredText($input, 'campaignType', 16))
            ?? throw new InvalidArgumentException('Choose newsletter or outreach as campaignType.');
        $structuredContent = array_key_exists('structuredContent', $input) ? $this->structuredContent($input['structuredContent'], null) : null;
        $blocks = $this->blocks($input['introBlocks'] ?? null, []);
        $mainText = null !== $structuredContent ? '' : (null === $blocks ? $this->requiredText($input, 'mainText', 20000) : MailingIntroBlockProcessor::plainText($blocks));
        if (null === $structuredContent) {
            $structuredContent = MailingCampaignContent::fromInitialBlocks($campaignType,
                $blocks ?? [['id' => 'initial', 'kind' => 'paragraph', 'text' => $mainText]],
            );
        }
        $campaign = new MailingCampaign(
            internalTitle: $this->requiredText($input, 'internalTitle', 255),
            emailSubject: $this->requiredText($input, 'emailSubject', 255),
            publicTitle: $this->requiredText($input, 'publicTitle', 255),
            mainText: $mainText,
            templateKey: $campaignType->value,
            campaignType: $campaignType,
            structuredContent: $structuredContent,
            audienceFilter: NewsletterAudienceFilter::empty(),
            subtitle: $this->optionalText($input, 'subtitle', 255),
            callToActionLabel: $this->optionalText($input, 'callToActionLabel', 255),
            callToActionUrl: $this->optionalUrl($input, 'callToActionUrl'),
            preheader: $this->optionalText($input, 'preheader', 255),
            introBlocks: null,
            uuid: $uuid,
        );
        if (isset($input['audienceMaskUuid'])) {
            $campaign->applyAudienceMask($this->findMask($input['audienceMaskUuid']));
        }
        $this->campaignRepository->save($campaign);

        return $this->view($campaign);
    }

    /** @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(string $campaignUuid, array $input): array
    {
        $this->assertKeys($input, ['campaignType', 'structuredContent', 'revision', 'internalTitle', 'emailSubject', 'publicTitle', 'mainText', 'introBlocks', 'preheader', 'subtitle', 'callToActionLabel', 'callToActionUrl', 'audienceMaskUuid', 'recommendations']);
        $campaign = $this->findCampaign($campaignUuid);
        if (!$campaign->isEditable()) {
            throw new InvalidArgumentException('This mailing has already entered delivery and cannot be edited.');
        }
        $revision = $this->requiredText($input, 'revision', 64);
        if (!hash_equals($this->revision($campaign), $revision)) {
            throw new InvalidArgumentException('This mailing changed since it was read. Read it again before editing.');
        }

        if (isset($input['campaignType'])) {
            $campaign->setCampaignType(MailingCampaignType::tryFrom($this->requiredText($input, 'campaignType', 16))
                ?? throw new InvalidArgumentException('Choose newsletter or outreach as campaignType.'));
        }
        if (array_key_exists('structuredContent', $input)) {
            $campaign->setStructuredContent($this->structuredContent($input['structuredContent'], $campaign->getStructuredContent()));
        }
        if (null !== $campaign->getStructuredContent() && (array_key_exists('introBlocks', $input) || array_key_exists('mainText', $input))) {
            throw new InvalidArgumentException('Edit structuredContent for this campaign; legacy text fields cannot overwrite sections.');
        }
        if (array_key_exists('introBlocks', $input) && !is_array($input['introBlocks'])) {
            throw new InvalidArgumentException('Invalid introduction blocks.');
        }
        $blocks = array_key_exists('introBlocks', $input) ? $this->blocks($input['introBlocks'], $campaign->getIntroBlocks() ?? []) : $campaign->getIntroBlocks();
        $mainText = null === $blocks
            ? (array_key_exists('mainText', $input) ? $this->requiredText($input, 'mainText', 20000) : $campaign->getMainText())
            : MailingIntroBlockProcessor::plainText($blocks);
        $campaign->updateContent(
            internalTitle: array_key_exists('internalTitle', $input) ? $this->requiredText($input, 'internalTitle', 255) : $campaign->getInternalTitle(),
            emailSubject: array_key_exists('emailSubject', $input) ? $this->requiredText($input, 'emailSubject', 255) : $campaign->getEmailSubject(),
            publicTitle: array_key_exists('publicTitle', $input) ? $this->requiredText($input, 'publicTitle', 255) : $campaign->getPublicTitle(),
            mainText: $mainText,
            templateKey: $campaign->getTemplateKey(),
            subtitle: array_key_exists('subtitle', $input) ? $this->optionalText($input, 'subtitle', 255) : $campaign->getSubtitle(),
            callToActionLabel: array_key_exists('callToActionLabel', $input) ? $this->optionalText($input, 'callToActionLabel', 255) : $campaign->getCallToActionLabel(),
            callToActionUrl: array_key_exists('callToActionUrl', $input) ? $this->optionalUrl($input, 'callToActionUrl') : $campaign->getCallToActionUrl(),
            bannerImagePath: $campaign->getBannerImagePath(),
            preheader: array_key_exists('preheader', $input) ? $this->optionalText($input, 'preheader', 255) : $campaign->getPreheader(),
            introBlocks: $blocks,
        );
        if (array_key_exists('audienceMaskUuid', $input)) {
            null === $input['audienceMaskUuid']
                ? $campaign->updateAudienceFilter(NewsletterAudienceFilter::empty())
                : $campaign->applyAudienceMask($this->findMask($input['audienceMaskUuid']));
        }
        if (array_key_exists('recommendations', $input)) {
            $recommendations = $this->recommendations($input['recommendations'], $campaign->getRecommendations());
            $campaign->replaceRecommendations($recommendations);
        }
        $this->campaignRepository->save($campaign);

        return $this->view($campaign);
    }

    private function structuredContent(mixed $value, ?MailingCampaignContent $previous): MailingCampaignContent
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('structuredContent must be a versioned object.');
        }
        $content = MailingCampaignContent::fromArray($value);
        $sections = $content->toArray()['sections'];
        foreach ($sections as $key => &$section) {
            $section['blocks'] = $this->blocks($section['blocks'], $previous?->getBlocks($key) ?? []) ?? [];
        }
        unset($section);
        foreach ($previous?->toArray()['sections'] ?? [] as $key => $previousSection) {
            if (!array_key_exists($key, $sections)) {
                $sections[$key] = $previousSection;
            }
        }

        return MailingCampaignContent::fromArray(['version' => 1, 'sections' => $sections]);
    }

    private function findCampaign(string $uuid): MailingCampaign
    {
        if (!Uuid::isValid($uuid)) {
            throw new InvalidArgumentException('Invalid mailing campaign UUID.');
        }
        $campaign = $this->campaignRepository->findByUuid(Uuid::fromString($uuid));
        if (null === $campaign) {
            throw new InvalidArgumentException('Mailing campaign not found.');
        }

        return $campaign;
    }

    private function findMask(mixed $uuid): MailingAudienceMask
    {
        if (!is_string($uuid) || !Uuid::isValid($uuid)) {
            throw new InvalidArgumentException('Invalid audience mask UUID.');
        }
        $mask = $this->maskRepository->findByUuid(Uuid::fromString($uuid));
        if (null === $mask) {
            throw new InvalidArgumentException('Audience mask not found.');
        }

        return $mask;
    }

    /** @param list<array<string, mixed>> $existing
     * @return list<array<string, mixed>>|null
     */
    private function blocks(mixed $value, array $existing): ?array
    {
        if (null === $value) {
            return null;
        }
        if (!is_array($value) || !array_is_list($value) || 20 < count($value)) {
            throw new InvalidArgumentException('Invalid introduction blocks.');
        }
        $existingById = [];
        foreach ($existing as $block) {
            $existingById[$block['id']] = $block;
        }
        $blocks = [];
        $seen = [];
        foreach ($value as $block) {
            if (!is_array($block)) {
                throw new InvalidArgumentException('Invalid introduction block.');
            }
            $this->assertKeys($block, ['id', 'kind', 'text', 'imagePath', 'imageAlt', 'imageDecorative', 'imageSide']);
            $id = $this->requiredText($block, 'id', 64);
            if (1 !== preg_match('/^[a-zA-Z0-9-]+$/D', $id) || isset($seen[$id])) {
                throw new InvalidArgumentException('Invalid or repeated introduction block ID.');
            }
            $seen[$id] = true;
            $kind = $block['kind'] ?? null;
            if (!in_array($kind, ['paragraph', 'split', 'image'], true)) {
                throw new InvalidArgumentException('Invalid introduction block kind.');
            }
            $previous = $existingById[$id] ?? null;
            if ('paragraph' !== $kind && (null === $previous || $previous['kind'] !== $kind)) {
                throw new InvalidArgumentException('New image blocks cannot be added through MCP.');
            }
            if (null !== $previous && $previous['kind'] !== $kind) {
                throw new InvalidArgumentException('Introduction block kind cannot change.');
            }
            foreach (['imagePath', 'imageAlt', 'imageDecorative', 'imageSide'] as $metadataKey) {
                if (array_key_exists($metadataKey, $block) && ($previous[$metadataKey] ?? null) !== $block[$metadataKey]) {
                    throw new InvalidArgumentException('Existing image metadata cannot be changed through MCP.');
                }
            }
            $next = $previous ?? ['id' => $id, 'kind' => $kind];
            if ('image' !== $kind) {
                $text = $this->requiredText($block, 'text', 10000);
                $formatted = $this->richTextFormatter->format($text);
                if ('' === $formatted->text) {
                    throw new InvalidArgumentException('Introduction block needs text.');
                }
                $next['text'] = $formatted->html;
            } elseif (array_key_exists('text', $block)) {
                throw new InvalidArgumentException('Image blocks cannot contain text.');
            }
            $blocks[] = $next;
        }
        foreach ($existing as $block) {
            if (in_array($block['kind'], ['image', 'split'], true) && !isset($seen[$block['id']])) {
                throw new InvalidArgumentException('Include existing image blocks in their original order when editing introduction blocks.');
            }
        }

        return $blocks;
    }

    /** @param list<MailingRecommendation> $existing
     * @return list<MailingRecommendation>
     */
    private function recommendations(mixed $value, array $existing): array
    {
        if (!is_array($value) || !array_is_list($value) || 10 < count($value)) {
            throw new InvalidArgumentException('Invalid recommendations.');
        }
        $existingById = [];
        foreach ($existing as $item) {
            $existingById[(string) $item->getUuid()] = $item;
        }
        $items = [];
        $used = [];
        foreach ($value as $index => $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Invalid recommendation.');
            }
            $this->assertKeys($item, ['recommendationUuid', 'sourceRecommendationUuid', 'title', 'tag', 'text', 'url', 'linkLabel', 'imagePath', 'position', 'active']);
            $id = $item['recommendationUuid'] ?? null;
            if (null !== $id && (!is_string($id) || !isset($existingById[$id]) || isset($used[$id]))) {
                throw new InvalidArgumentException('Invalid recommendation UUID.');
            }
            $title = $this->requiredText($item, 'title', 255);
            $text = $this->requiredText($item, 'text', RecommendationText::MAX_SOURCE_LENGTH);
            if ('' === RecommendationText::trimmedVisibleText($text)) {
                throw new InvalidArgumentException('Recommendation text must contain visible text.');
            }
            if (RecommendationText::MAX_VISIBLE_LENGTH < mb_strlen(RecommendationText::visibleText($text)) && (null === $id || $text !== $existingById[$id]->getText())) {
                throw new InvalidArgumentException('Recommendation text exceeds 420 visible characters.');
            }
            $tag = $this->optionalText($item, 'tag', 255);
            $url = $this->optionalUrl($item, 'url');
            $linkLabel = $this->optionalText($item, 'linkLabel', 255);
            if (null !== $id) {
                $used[$id] = true;
                $recommendation = $existingById[$id];
                if (array_key_exists('sourceRecommendationUuid', $item) && $item['sourceRecommendationUuid'] !== $recommendation->getSourceRecommendationUuid()?->toRfc4122()) {
                    throw new InvalidArgumentException('Mailing recommendation source cannot be changed.');
                }
                if ((array_key_exists('imagePath', $item) && $item['imagePath'] !== $recommendation->getImagePath())
                    || (array_key_exists('position', $item) && $item['position'] !== $recommendation->getPosition())) {
                    throw new InvalidArgumentException('Existing recommendation media and position cannot be changed through MCP.');
                }
                $recommendation->updateContent($title, $tag, $text);
                $recommendation->updateLink($url, $linkLabel);
                $recommendation->moveToPosition($index + 1);
                if (array_key_exists('active', $item)) {
                    if (!is_bool($item['active'])) {
                        throw new InvalidArgumentException('Invalid recommendation active state.');
                    }
                    $recommendation->setActive($item['active']);
                }
            } else {
                throw new InvalidArgumentException('Add recommendations from the catalog with add_catalog_recommendation_to_mailing.');
            }
            $items[] = $recommendation;
        }

        return $items;
    }

    /** @return array<string, mixed> */
    private function view(MailingCampaign $campaign): array
    {
        return [
            'campaignUuid' => (string) $campaign->getUuid(),
            'campaignType' => $campaign->getCampaignType()?->value,
            'structuredContent' => $campaign->getStructuredContent()?->toArray(),
            'status' => $campaign->getStatus()->value,
            'editable' => $campaign->isEditable(),
            'revision' => $this->revision($campaign),
            'internalTitle' => $campaign->getInternalTitle(),
            'emailSubject' => $campaign->getEmailSubject(),
            'publicTitle' => $campaign->getPublicTitle(),
            'preheader' => $campaign->getPreheader(),
            'subtitle' => $campaign->getSubtitle(),
            'mainText' => $campaign->getMainText(),
            'introBlocks' => $campaign->getIntroBlocks(),
            'callToActionLabel' => $campaign->getCallToActionLabel(),
            'callToActionUrl' => $campaign->getCallToActionUrl(),
            'bannerImagePath' => $campaign->getBannerImagePath(),
            'templateKey' => $campaign->getTemplateKey(),
            'audienceMaskUuid' => null === $campaign->getAppliedAudienceMaskUuid() ? null : (string) $campaign->getAppliedAudienceMaskUuid(),
            'audienceMaskName' => $campaign->getAppliedAudienceMaskName(),
            'recommendations' => array_map(static fn (MailingRecommendation $item): array => [
                'recommendationUuid' => (string) $item->getUuid(),
                'sourceRecommendationUuid' => $item->getSourceRecommendationUuid()?->toRfc4122(),
                'title' => $item->getTitle(),
                'tag' => $item->getTag(),
                'text' => $item->getText(),
                'url' => $item->getUrl(),
                'linkLabel' => $item->getLinkLabel(),
                'imagePath' => $item->getImagePath(),
                'position' => $item->getPosition(),
                'active' => $item->isActive(),
            ], $campaign->getRecommendations()),
            'updatedAt' => $campaign->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    private function revision(MailingCampaign $campaign): string
    {
        try {
            return hash('sha256', json_encode([
                $campaign->getCampaignType()?->value, $campaign->getStructuredContent()?->toArray(),
                $campaign->getStatus()->value, $campaign->getInternalTitle(), $campaign->getEmailSubject(),
                $campaign->getPublicTitle(), $campaign->getPreheader(), $campaign->getSubtitle(),
                $campaign->getMainText(), $campaign->getIntroBlocks(), $campaign->getCallToActionLabel(),
                $campaign->getCallToActionUrl(), (string) $campaign->getAppliedAudienceMaskUuid(),
                array_map(static fn (MailingRecommendation $item): array => [
                    (string) $item->getUuid(), $item->getTitle(), $item->getTag(), $item->getText(), $item->getUrl(),
                    $item->getLinkLabel(), $item->getImagePath(), $item->getPosition(), $item->isActive(),
                ], $campaign->getRecommendations()),
            ], JSON_THROW_ON_ERROR));
        } catch (JsonException) {
            throw new InvalidArgumentException('Mailing cannot be read.');
        }
    }

    /** @param array<string, mixed> $input */
    private function requiredText(array $input, string $key, int $maxLength): string
    {
        $value = $input[$key] ?? null;
        if (!is_string($value) || '' === trim($value) || mb_strlen($value) > $maxLength || 1 === preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            throw new InvalidArgumentException("Invalid {$key}.");
        }

        return trim($value);
    }

    /** @param array<string, mixed> $input */
    private function optionalText(array $input, string $key, int $maxLength): ?string
    {
        if (null === ($input[$key] ?? null)) {
            return null;
        }

        return $this->requiredText($input, $key, $maxLength);
    }

    /** @param array<string, mixed> $input */
    private function optionalUrl(array $input, string $key): ?string
    {
        $url = $this->optionalText($input, $key, 2048);
        if (null !== $url && (false === filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?: ''), ['http', 'https'], true))) {
            throw new InvalidArgumentException("Invalid {$key} URL.");
        }

        return $url;
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
}
