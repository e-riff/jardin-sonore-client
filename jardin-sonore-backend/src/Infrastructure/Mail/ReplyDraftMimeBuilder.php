<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Application\Mcp\ReplyContext;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Mime\Email;

final readonly class ReplyDraftMimeBuilder
{
    public function build(ReplyContext $context, string $body, string $draftMessageId, string $mailboxAddress, DateTimeImmutable $createdAt, ?string $htmlBody = null): string
    {
        $subject = trim($context->subject ?? '');
        if (1 === preg_match('/[\x00-\x1F\x7F]/', $subject) || 1 !== preg_match('/^<[^<>\s]{1,250}>$/D', $draftMessageId)) {
            throw new InvalidArgumentException('Invalid draft headers.');
        }
        if ('' === trim($body) || 10_000 < mb_strlen($body)) {
            throw new InvalidArgumentException('Invalid draft body.');
        }

        $email = (new Email())
            ->from($mailboxAddress)
            ->to($context->replyRecipient)
            ->subject($context->replySubject())
            ->date($createdAt)
            ->text($body, 'utf-8');
        $headers = $email->getHeaders();
        if (null !== $htmlBody) {
            $email->html(DraftHtmlBody::sanitize($htmlBody), 'utf-8');
        }
        $headers->addIdHeader('Message-ID', trim($draftMessageId, '<>'));
        if (null !== $context->messageId) {
            $headers->addIdHeader('In-Reply-To', trim($context->messageId, '<>'));
            $referenceIds = [];
            if (null !== $context->references) {
                preg_match_all('/<([^<>\s]+)>/', $context->references, $matches);
                $referenceIds = $matches[1];
            }
            $referenceIds[] = trim($context->messageId, '<>');
            $headers->addIdHeader('References', array_values(array_unique($referenceIds)));
        }

        return $email->toString();
    }
}
