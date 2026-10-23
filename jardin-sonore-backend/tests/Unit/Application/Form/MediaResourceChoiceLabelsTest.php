<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Form;

use App\Application\Form\MediaResourceChoiceLabels;
use App\Domain\Model\Session\MediaResource;
use App\Domain\Model\Session\MediaResourceType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

final class MediaResourceChoiceLabelsTest extends TestCase
{
    public function testEveryResultIncludesTypeAndSourceEvenWhenTheTitleIsUnique(): void
    {
        $mediaResource = new MediaResource(
            type: MediaResourceType::VIDEO,
            title: 'Sui lo ruin',
            primaryUrl: 'https://www.youtube.com/watch?v=one',
            source: 'Hélène Bohy',
            id: 234,
        );
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['sessions.media.type.video' => 'Vidéo'], 'fr', 'sessions');

        $labels = MediaResourceChoiceLabels::byUuid([$mediaResource], $translator);

        self::assertSame('234 — Sui lo ruin — Vidéo — Hélène Bohy', $labels[$mediaResource->getUuid()->toRfc4122()]);
    }

    public function testNewUsageCategoriesCanBeReadFromStoredValues(): void
    {
        self::assertNotNull(MediaResourceType::tryFrom('ambient_sound'));
        self::assertNotNull(MediaResourceType::tryFrom('score'));
    }
}
