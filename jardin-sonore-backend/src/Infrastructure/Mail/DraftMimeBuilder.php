<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Application\Mcp\MailDraft;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

final readonly class DraftMimeBuilder
{
    public function build(MailDraft $draft, string $mailboxAddress): string
    {
        $email = (new Email())->from($mailboxAddress)->subject($draft->subject ?? '')->text($draft->body, 'utf-8');
        $to = array_values(array_filter([$draft->recipient, ...$draft->additionalRecipients]));
        if ([] !== $to) {
            $email->to(...$to);
        }
        if ([] !== $draft->cc) {
            $email->cc(...$draft->cc);
        }
        if ([] !== $draft->bcc) {
            $email->bcc(...$draft->bcc);
        }
        if (null !== $draft->htmlBody) {
            // Existing HTML and CID images must survive a text-only update unchanged.
            $email->html($draft->htmlBody, 'utf-8');
        }
        $email->getHeaders()->addIdHeader('Message-ID', trim($draft->messageId, '<>'));
        foreach (['In-Reply-To' => $draft->inReplyTo, 'References' => $draft->references] as $name => $value) {
            if (null !== $value && 0 < preg_match_all('/<([^<>\s]+)>/', $value, $matches)) {
                $email->getHeaders()->addIdHeader($name, $matches[1]);
            }
        }
        foreach ($draft->attachments as $attachment) {
            $part = new DataPart($attachment->content, $attachment->filename, $attachment->mimeType);
            if (null !== $attachment->contentId) {
                $part->setContentId($attachment->contentId);
            }
            if ($attachment->inline) {
                $part->asInline();
            }
            $email->addPart($part);
        }

        // Email::toString strips Bcc for sending; drafts must retain the editable header.
        $headers = $email->getPreparedHeaders();
        if ([] !== $draft->bcc) {
            $headers->addMailboxListHeader('Bcc', $email->getBcc());
        }

        return $headers->toString() . $email->getBody()->toString();
    }
}
