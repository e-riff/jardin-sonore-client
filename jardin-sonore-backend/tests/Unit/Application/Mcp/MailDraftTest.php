<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mcp;

use App\Application\Mcp\MailDraft;
use PHPUnit\Framework\TestCase;

final class MailDraftTest extends TestCase
{
    public function testRevisionDetectsHtmlChangesWithoutChangingPlainTextLegacyTokens(): void
    {
        $plainDraft = new MailDraft(1, '<plain@example.test>', 'Objet', 'marie@example.test', 'Bonjour');
        $htmlDraft = new MailDraft(2, '<html@example.test>', 'Objet', 'marie@example.test', 'Bonjour', '<p>Bonjour</p>');

        self::assertSame(hash('sha256', 'Bonjour'), $plainDraft->revision());
        self::assertNotSame($plainDraft->revision(), $htmlDraft->revision());
        self::assertNotSame($htmlDraft->revision(), MailDraft::contentRevision('Bonjour', '<p><strong>Bonjour</strong></p>'));
    }
}
