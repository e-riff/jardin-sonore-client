<?php

declare(strict_types=1);

namespace App\Domain\Model\Mailing;

use InvalidArgumentException;

final readonly class MailingCampaignContent
{
    public const array NEWSLETTER_SECTIONS = ['editorial', 'monthly_idea', 'jardin_sonore', 'recommendations'];
    public const array OUTREACH_SECTIONS = ['proposal', 'approach', 'recommendations'];

    /** @param array<string, array{enabled: bool, blocks: list<array<string, mixed>>}> $sections */
    private function __construct(private array $sections)
    {
    }

    /** @param list<array<string, mixed>> $blocks */
    public static function fromInitialBlocks(MailingCampaignType $campaignType, array $blocks): self
    {
        $keys = MailingCampaignType::NEWSLETTER === $campaignType ? self::NEWSLETTER_SECTIONS : self::OUTREACH_SECTIONS;
        $sections = [];
        foreach ($keys as $index => $key) {
            $sections[$key] = ['enabled' => true, 'blocks' => 0 === $index ? $blocks : []];
        }

        return self::fromArray(['version' => 1, 'sections' => $sections]);
    }

    /** @param array<string, mixed> $content */
    public static function fromArray(array $content): self
    {
        if (1 !== ($content['version'] ?? null) || !is_array($content['sections'] ?? null)) {
            throw new InvalidArgumentException('Unsupported mailing content version.');
        }

        $sections = [];
        foreach ($content['sections'] as $key => $section) {
            if (!in_array($key, array_merge(self::NEWSLETTER_SECTIONS, self::OUTREACH_SECTIONS), true)
                || !is_array($section) || !is_bool($section['enabled'] ?? null)
                || !is_array($section['blocks'] ?? null) || !array_is_list($section['blocks'])) {
                throw new InvalidArgumentException('Invalid mailing content section.');
            }

            $seenIds = [];
            foreach ($section['blocks'] as $block) {
                if (!is_array($block) || !is_string($block['id'] ?? null)
                    || 1 !== preg_match('/^[a-zA-Z0-9-]{1,64}$/', $block['id'])
                    || isset($seenIds[$block['id']]) || !in_array($block['kind'] ?? null, ['paragraph', 'image', 'split'], true)) {
                    throw new InvalidArgumentException('Invalid mailing section block.');
                }
                $seenIds[$block['id']] = true;
                if (in_array($block['kind'], ['paragraph', 'split'], true) && !is_string($block['text'] ?? null)) {
                    throw new InvalidArgumentException('Mailing section text must be a string.');
                }
                if (in_array($block['kind'], ['image', 'split'], true)) {
                    if (!is_string($block['imagePath'] ?? null)
                        || 1 !== preg_match('~^uploads/mailing/content/[a-z0-9._-]+$~i', $block['imagePath'])
                        || (true !== ($block['imageDecorative'] ?? false)
                            && (!is_string($block['imageAlt'] ?? null) || '' === trim($block['imageAlt'])))) {
                        throw new InvalidArgumentException('Invalid mailing section image.');
                    }
                }
                if ('split' === $block['kind'] && !in_array($block['imageSide'] ?? null, ['left', 'right'], true)) {
                    throw new InvalidArgumentException('Invalid mailing section image side.');
                }
            }
            if ('recommendations' === $key && [] !== $section['blocks']) {
                throw new InvalidArgumentException('Recommendations must use the mailing catalog.');
            }
            $sections[$key] = ['enabled' => $section['enabled'], 'blocks' => $section['blocks']];
        }

        return new self($sections);
    }

    /** @return array{version: int, sections: array<string, array{enabled: bool, blocks: list<array<string, mixed>>}>} */
    public function toArray(): array
    {
        return ['version' => 1, 'sections' => $this->sections];
    }

    public function isSectionEnabled(string $sectionKey): bool
    {
        return $this->sections[$sectionKey]['enabled'] ?? false;
    }

    /** @return list<array<string, mixed>> */
    public function getBlocks(string $sectionKey): array
    {
        return $this->sections[$sectionKey]['blocks'] ?? [];
    }

    public function hasVisibleText(string $sectionKey): bool
    {
        if (!$this->isSectionEnabled($sectionKey)) {
            return false;
        }
        foreach ($this->getBlocks($sectionKey) as $block) {
            $html = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', (string) ($block['text'] ?? '')) ?? '';
            $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
            if ('' !== trim(str_replace("\u{00A0}", ' ', $text))) {
                return true;
            }
        }

        return false;
    }

    public function assertMatchesType(MailingCampaignType $campaignType): void
    {
        $allowedKeys = MailingCampaignType::NEWSLETTER === $campaignType ? self::NEWSLETTER_SECTIONS : self::OUTREACH_SECTIONS;
        if ([] !== array_diff(array_keys($this->sections), $allowedKeys)) {
            throw new InvalidArgumentException('Mailing sections do not match the campaign type.');
        }
    }
}
