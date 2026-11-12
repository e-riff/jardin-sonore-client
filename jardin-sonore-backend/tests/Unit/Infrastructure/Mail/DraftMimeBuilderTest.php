<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Mail;

use App\Application\Mcp\MailDraft;
use App\Application\Mcp\MailDraftAttachment;
use App\Infrastructure\Mail\DraftMessageMapper;
use App\Infrastructure\Mail\DraftMimeBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Webklex\PHPIMAP\Message;

final class DraftMimeBuilderTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function subjectHeaders(): iterable
    {
        yield 'raw utf8 mixed with an encoded word' => ['Crèche =?UTF-8?Q?=C3=A9veil?= — merci', 'Crèche éveil — merci'];
        yield 'adjacent folded encoded words' => ["=?UTF-8?B?w6l2ZWls?=\r\n =?UTF-8?Q?_musical_=E2=80=94_cr=C3=A8che?=", 'éveil musical — crèche'];
        yield 'latin1 encoded word' => ['=?ISO-8859-1?Q?cr=E8che?=', 'crèche'];
        yield 'encoded zero' => ['=?UTF-8?B?MA==?=', '0'];
    }

    #[DataProvider('subjectHeaders')]
    public function testDecodesOnlyMimeEncodedWordsWithoutLosingRawUtf8(string $rawSubject, string $expectedSubject): void
    {
        $message = Message::fromString("From: contact@example.test\r\nTo: marie@example.test\r\nSubject: {$rawSubject}\r\n\r\nBonjour");
        self::assertSame($expectedSubject, (new DraftMessageMapper())->subject($message));
    }

    public function testAlreadyDecodedUtf8SubjectKeepsAllAccents(): void
    {
        $subject = 'TEST connecteur (é è à ç œ) — crèche';
        $message = Message::fromString("Message-ID: <test@example.test>\r\nFrom: contact@example.test\r\nTo: marie@example.test\r\nSubject: {$subject}\r\n\r\nBonjour");
        $message->uid = 42;
        self::assertSame($subject, (new DraftMessageMapper())->map($message, '<test@example.test>')->subject);
    }

    public function testPreservesRealContentIdWhenVendorAttachmentIdIsDifferent(): void
    {
        $draft = new MailDraft(1, '<draft@example.test>', 'Objet', 'first@example.test', 'Bonjour', '<p><img src="cid:photo@example.test">Bonjour</p>', attachments: [new MailDraftAttachment('photo.png', 'image/png', 'PNG', 'photo@example.test', true)]);
        $mime = (new DraftMimeBuilder())->build($draft, 'contact@example.test');
        $mime = str_replace('Content-ID: <photo@example.test>', "Content-ID: <photo@example.test>\r\nX-Attachment-Id: vendor-id", $mime);
        $message = Message::fromString($mime);
        $message->uid = 1;
        $parsed = (new DraftMessageMapper())->map($message, $draft->messageId);
        self::assertSame('photo@example.test', $parsed->attachments[0]->contentId);
        $replacementMime = (new DraftMimeBuilder())->build($parsed, 'contact@example.test');
        self::assertStringContainsString('Content-ID: <photo@example.test>', $replacementMime);
        self::assertStringContainsString('cid:photo@example.test', $replacementMime);
    }

    public function testMimeLineEndingNormalizationDoesNotCreateAFalseConflict(): void
    {
        $draft = new MailDraft(1, '<draft@example.test>', 'Objet', 'first@example.test', "Bonjour\nDeuxième ligne", "<p>Bonjour</p>\n<p>Deuxième ligne</p>");
        $message = Message::fromString((new DraftMimeBuilder())->build($draft, 'contact@example.test'));
        $message->uid = 1;
        self::assertSame($draft->snapshotRevision(), (new DraftMessageMapper())->map($message, $draft->messageId)->snapshotRevision());
    }

    public function testMimeRoundTripPreservesTheFullDraftIncludingInlineAndBcc(): void
    {
        $draft = new MailDraft(1, '<draft@example.test>', 'Devis été', 'first@example.test', 'Bonjour', '<p>Bonjour<img src="cid:photo@example.test"></p>', ['second@example.test'], ['cc@example.test'], ['bcc@example.test'], [
            new MailDraftAttachment('devis.pdf', 'application/pdf', '%PDF-1.7'),
            new MailDraftAttachment('photo.png', 'image/png', 'PNG', 'photo@example.test', true),
        ], '<source@example.test>', '<root@example.test> <source@example.test>');
        $mime = (new DraftMimeBuilder())->build($draft, 'contact@example.test');
        $message = Message::fromString($mime);
        $message->uid = 1;
        $parsed = (new DraftMessageMapper())->map($message, $draft->messageId);
        self::assertSame($draft->snapshotRevision(), $parsed->snapshotRevision());
        self::assertSame(['bcc@example.test'], $parsed->bcc);
        $files = [];
        foreach ($parsed->attachments as $attachment) {
            $files[$attachment->filename] = $attachment;
        }
        self::assertSame('%PDF-1.7', $files['devis.pdf']->content);
        self::assertSame('photo@example.test', $files['photo.png']->contentId);
    }
}
