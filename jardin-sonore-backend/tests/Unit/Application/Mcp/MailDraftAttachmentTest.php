<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mcp;

use App\Application\Mcp\MailDraftAttachment;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MailDraftAttachmentTest extends TestCase
{
    public function testAcceptsAFileAtTheFiveMiBLimit(): void
    {
        $content = str_repeat('a', 5 * 1024 * 1024);
        $attachment = MailDraftAttachment::fromPayload(['filename' => 'devis.pdf', 'mimeType' => 'application/pdf', 'base64' => base64_encode($content)]);
        self::assertSame($content, $attachment->content);
    }

    public function testRejectsOneByteOverTheFileLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MailDraftAttachment::fromPayload(['filename' => 'devis.pdf', 'mimeType' => 'application/pdf', 'base64' => base64_encode(str_repeat('a', 5 * 1024 * 1024 + 1))]);
    }

    public function testRejectsPathLikeFilenames(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MailDraftAttachment::fromPayload(['filename' => '../devis.pdf', 'mimeType' => 'application/pdf', 'base64' => 'UERG']);
    }

    public function testRejectsMoreThanTenFiles(): void
    {
        $attachment = new MailDraftAttachment('x', 'application/pdf', 'PDF');
        $this->expectException(InvalidArgumentException::class);
        MailDraftAttachment::assertLimits(array_fill(0, 11, $attachment));
    }

    public function testRejectsMoreThanTenMiBCombined(): void
    {
        $attachment = new MailDraftAttachment('x', 'application/pdf', str_repeat('a', 4 * 1024 * 1024));
        $this->expectException(InvalidArgumentException::class);
        MailDraftAttachment::assertLimits([$attachment, $attachment, $attachment]);
    }
}
