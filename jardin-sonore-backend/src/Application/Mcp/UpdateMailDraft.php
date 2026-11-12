<?php

declare(strict_types=1);

namespace App\Application\Mcp;

use App\Infrastructure\Mail\DraftHtmlBody;
use App\Infrastructure\Mail\DraftMimeBuilder;
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
        private DraftMimeBuilder $mimeBuilder,
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
        $requestKey = substr(hash('sha256', $context->sourceKey . "\0" . $expectedDraftMessageId . "\0" . $expectedRevision . "\0" . $body . (null === $htmlBody ? '' : "\0" . $htmlBody)), 0, 24);
        $pendingMessageId = $receipt->pendingMessageId ?? '';
        if ('updating' === $receipt->state && !str_starts_with($pendingMessageId, "<mcp-edit-{$requestKey}-")) {
            return ['status' => 'update_in_progress', 'nextAction' => 'search_mail_drafts'];
        }
        if ('updating' !== $receipt->state) {
            if ('saved' !== $receipt->state) {
                return ['status' => 'unavailable'];
            }
            $currentDraft = $this->draftStore->getByMessageId($receipt->folder, $receipt->messageId);
            if (null === $currentDraft) {
                return ['status' => 'missing'];
            }
            if (!hash_equals($expectedRevision, $currentDraft->snapshotRevision())) {
                return ['status' => 'conflict'];
            }
            $desired = $this->desiredDraft($currentDraft, $body, $htmlBody, $receipt->folder);
            // Persist the desired full snapshot in the replacement ID before touching IMAP.
            $pendingMessageId = "<mcp-edit-{$requestKey}-{$desired->snapshotRevision()}@{$domain}>";
            if (!$this->receiptStore->beginUpdate($context->sourceKey, $receipt->messageId, $pendingMessageId)) {
                return ['status' => 'conflict'];
            }
            $receipt = $this->receiptStore->find($context->sourceKey);
            if (null === $receipt) {
                return ['status' => 'uncertain'];
            }
        }

        if (1 !== preg_match('/^<mcp-edit-[a-f0-9]{24}-([a-f0-9]{64})@[^<>\s]+>$/D', $pendingMessageId, $matches)) {
            return ['status' => 'uncertain', 'nextAction' => 'search_mail_drafts'];
        }

        return $this->replaceDraft($context, $receipt, $pendingMessageId, $matches[1], $expectedRevision, $body, $htmlBody);
    }

    /** @return array<string, mixed> */
    private function replaceDraft(ReplyContext $context, DraftReceipt $receipt, string $pendingMessageId, string $desiredRevision, string $expectedRevision, string $body, ?string $htmlBody): array
    {
        try {
            $pendingDraft = $this->draftStore->getByMessageId($receipt->folder, $pendingMessageId);
            $currentDraft = $this->draftStore->getByMessageId($receipt->folder, $receipt->messageId);
            if (null === $currentDraft) {
                if (null !== $pendingDraft && hash_equals($desiredRevision, $pendingDraft->snapshotRevision())
                    && $this->receiptStore->completeUpdate($context->sourceKey, $pendingMessageId, $pendingDraft->uid)) {
                    return $this->draftResult($pendingDraft, 'saved');
                }

                return ['status' => 'uncertain', 'nextAction' => 'search_mail_drafts'];
            }
            if (!hash_equals($expectedRevision, $currentDraft->snapshotRevision())) {
                return ['status' => 'conflict'];
            }
            $draftMimeBuilder = $this->mimeBuilder;
            $desired = $this->desiredDraft($currentDraft, $body, $htmlBody, $receipt->folder);
            if (!hash_equals($desiredRevision, $desired->snapshotRevision())) {
                return ['status' => 'conflict'];
            }
            $desired = new MailDraft(0, $pendingMessageId, $desired->subject, $desired->recipient, $desired->body, $desired->htmlBody, $desired->additionalRecipients, $desired->cc, $desired->bcc, $desired->attachments, $desired->inReplyTo, $desired->references);
            if (null === $pendingDraft) {
                $this->draftStore->append($receipt->folder, $draftMimeBuilder->build($desired, $this->mailboxAddress));
                $pendingDraft = $this->draftStore->getByMessageId($receipt->folder, $pendingMessageId);
            }
            if (null === $pendingDraft || !hash_equals($desired->snapshotRevision(), $pendingDraft->snapshotRevision())) {
                return ['status' => 'uncertain'];
            }

            $currentDraft = $this->draftStore->getByMessageId($receipt->folder, $receipt->messageId);
            if (null !== $currentDraft && !hash_equals($expectedRevision, $currentDraft->snapshotRevision())) {
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

    private function desiredDraft(MailDraft $currentDraft, string $body, ?string $htmlBody, string $folder): MailDraft
    {
        $changes = ['body' => $body];
        if (null !== $htmlBody) {
            $changes['htmlBody'] = $htmlBody;
        }

        return (new GeneralMailDraftEditor($this->draftStore, $this->mimeBuilder, $this->mailboxAddress, $folder))->applyChanges($currentDraft, $changes);
    }

    /** @return array<string, mixed> */
    private function draftResult(MailDraft $draft, string $state): array
    {
        return array_replace($draft->result(), ['status' => $state, 'draftMessageId' => $draft->messageId]);
    }
}
