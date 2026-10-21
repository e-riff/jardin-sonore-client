<?php

declare(strict_types=1);

namespace App\Application\Mcp;

use App\Infrastructure\Mail\DraftHtmlBody;
use App\Infrastructure\Mail\ReplyDraftMimeBuilder;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

final readonly class UpdateMailDraft implements MailDraftEditor
{
    public function __construct(
        private MailReader $mailReader,
        private MailDraftReceiptStore $receiptStore,
        private MailDraftStore $draftStore,
        private ReplyDraftMimeBuilder $mimeBuilder,
        #[Autowire('%app.mcp.imap_username%')] private string $mailboxAddress,
    ) {
    }

    /** @return array<string, mixed> */
    public function read(string $mailReference): array
    {
        $source = $this->mailReader->source($mailReference);
        $receipt = $this->receiptStore->find($source->sourceKey);
        if (null === $receipt) {
            return ['status' => 'not_found'];
        }

        $draft = $this->draftStore->getByMessageId($receipt->folder, $receipt->messageId);
        if (null === $draft && null !== $receipt->pendingMessageId) {
            $draft = $this->draftStore->getByMessageId($receipt->folder, $receipt->pendingMessageId);
        }
        if (null === $draft) {
            return ['status' => 'missing'];
        }

        return $this->draftResult($draft, $receipt->state);
    }

    /** @return array<string, mixed> */
    public function update(string $mailReference, string $expectedDraftMessageId, string $expectedRevision, string $body, ?string $htmlBody = null): array
    {
        if ('' === trim($body) || 10_000 < mb_strlen($body) || 64 !== strlen($expectedRevision) || 1 !== preg_match('/^[a-f0-9]{64}$/D', $expectedRevision)) {
            throw new InvalidArgumentException('Invalid draft update.');
        }
        if (false === filter_var($this->mailboxAddress, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Mailbox configuration is invalid.');
        }
        if (null !== $htmlBody) {
            $htmlBody = DraftHtmlBody::sanitize($htmlBody);
        }

        $context = $this->mailReader->replyContext($mailReference);
        $receipt = $this->receiptStore->find($context->sourceKey);
        if (null === $receipt) {
            return ['status' => 'not_found'];
        }
        if ($expectedDraftMessageId !== $receipt->messageId) {
            return ['status' => 'conflict'];
        }

        $domain = substr(strrchr($this->mailboxAddress, '@'), 1);
        $pendingMessageId = '<mcp-edit-' . $context->sourceKey . '-' . hash('sha256', $expectedDraftMessageId . "\0" . $body . (null === $htmlBody ? '' : "\0" . $htmlBody)) . '@' . $domain . '>';

        if ('updating' === $receipt->state && $receipt->pendingMessageId !== $pendingMessageId) {
            return ['status' => 'update_in_progress'];
        }
        if ('updating' !== $receipt->state) {
            if ('saved' !== $receipt->state) {
                return ['status' => 'unavailable'];
            }
            $currentDraft = $this->draftStore->getByMessageId($receipt->folder, $receipt->messageId);
            if (null === $currentDraft) {
                return ['status' => 'missing'];
            }
            if (!hash_equals($expectedRevision, $currentDraft->revision())) {
                return ['status' => 'conflict'];
            }
            if (!$this->receiptStore->beginUpdate($context->sourceKey, $receipt->messageId, $pendingMessageId)) {
                return ['status' => 'conflict'];
            }
            $receipt = $this->receiptStore->find($context->sourceKey);
            if (null === $receipt) {
                return ['status' => 'uncertain'];
            }
        }

        return $this->replaceDraft($context, $receipt, $pendingMessageId, $expectedRevision, $body, $htmlBody);
    }

    /** @return array<string, mixed> */
    private function replaceDraft(ReplyContext $context, DraftReceipt $receipt, string $pendingMessageId, string $expectedRevision, string $body, ?string $htmlBody): array
    {
        try {
            $pendingDraft = $this->draftStore->getByMessageId($receipt->folder, $pendingMessageId);
            if (null === $pendingDraft) {
                $currentDraft = $this->draftStore->getByMessageId($receipt->folder, $receipt->messageId);
                if (null === $currentDraft) {
                    return ['status' => 'uncertain'];
                }
                if (!hash_equals($expectedRevision, $currentDraft->revision())) {
                    return ['status' => 'conflict'];
                }
                $mime = $this->mimeBuilder->build($context, $body, $pendingMessageId, $this->mailboxAddress, new DateTimeImmutable(), $htmlBody);
                $this->draftStore->append($receipt->folder, $mime);
                $pendingDraft = $this->draftStore->getByMessageId($receipt->folder, $pendingMessageId);
            }
            if (null === $pendingDraft || !hash_equals(MailDraft::contentRevision($body, $htmlBody), $pendingDraft->revision())) {
                return ['status' => 'uncertain'];
            }

            $currentDraft = $this->draftStore->getByMessageId($receipt->folder, $receipt->messageId);
            if (null !== $currentDraft && !hash_equals($expectedRevision, $currentDraft->revision())) {
                return ['status' => 'conflict'];
            }
            if (null !== $currentDraft && !$this->draftStore->deleteByMessageId($receipt->folder, $receipt->messageId, $currentDraft->uid)) {
                return ['status' => 'uncertain', 'replacementVerified' => true, 'previousDraftRemoved' => false];
            }

            if (!$this->receiptStore->completeUpdate($context->sourceKey, $pendingMessageId, $pendingDraft->uid)) {
                return ['status' => 'uncertain', 'replacementVerified' => true, 'previousDraftRemoved' => null];
            }

            return $this->draftResult($pendingDraft, 'saved');
        } catch (Throwable) {
            return ['status' => 'uncertain'];
        }
    }

    /** @return array<string, mixed> */
    private function draftResult(MailDraft $draft, string $state): array
    {
        return [
            'status' => $state,
            'draftMessageId' => $draft->messageId,
            'revision' => $draft->revision(),
            'subject' => $draft->subject,
            'to' => $draft->recipient,
            'body' => $draft->body,
            'htmlBody' => $draft->htmlBody,
        ];
    }
}
