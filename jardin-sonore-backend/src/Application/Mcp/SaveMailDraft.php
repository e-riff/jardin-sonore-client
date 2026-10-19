<?php

declare(strict_types=1);

namespace App\Application\Mcp;

use App\Infrastructure\Mail\ReplyDraftMimeBuilder;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

final readonly class SaveMailDraft implements MailDraftWriter
{
    public function __construct(
        private MailReader $mailReader,
        private MailDraftReceiptStore $receiptStore,
        private MailDraftStore $draftStore,
        private ReplyDraftMimeBuilder $mimeBuilder,
        #[Autowire('%app.mcp.imap_username%')] private string $mailboxAddress,
        #[Autowire('%app.mcp.imap_drafts_folder%')] private string $draftsFolder,
    ) {
    }

    /** @return array{status: string, folder: string, messageId: string, uid: ?int} */
    public function save(string $mailReference, string $body): array
    {
        if ('' === trim($body) || 10_000 < mb_strlen($body) || false === filter_var($this->mailboxAddress, FILTER_VALIDATE_EMAIL) || '' === trim($this->draftsFolder)) {
            throw new InvalidArgumentException('Invalid draft request or mailbox configuration.');
        }

        $context = $this->mailReader->replyContext($mailReference);
        $domain = substr(strrchr($this->mailboxAddress, '@'), 1);
        $draftMessageId = "<mcp-draft-{$context->sourceKey}@{$domain}>";
        $mime = $this->mimeBuilder->build($context, $body, $draftMessageId, $this->mailboxAddress, new DateTimeImmutable());
        $receipt = $this->receiptStore->reserve($context->sourceKey, $draftMessageId, $this->draftsFolder);
        if (!$receipt->isNew) {
            $uid = $this->draftStore->findByMessageId($receipt->folder, $receipt->messageId);
            if (null !== $uid) {
                $this->receiptStore->markSaved($context->sourceKey, $uid);

                return $this->result('already_exists', $receipt->folder, $receipt->messageId, $uid);
            }

            return $this->result('saved' === $receipt->state ? 'missing' : 'uncertain', $receipt->folder, $receipt->messageId, null);
        }

        try {
            $this->draftStore->append($receipt->folder, $mime);
            $uid = $this->draftStore->findByMessageId($receipt->folder, $receipt->messageId);
            if (null === $uid) {
                $this->receiptStore->markUncertain($context->sourceKey);

                return $this->result('uncertain', $receipt->folder, $receipt->messageId, null);
            }
            $this->receiptStore->markSaved($context->sourceKey, $uid);

            return $this->result('saved', $receipt->folder, $receipt->messageId, $uid);
        } catch (Throwable) {
            $this->receiptStore->markUncertain($context->sourceKey);

            return $this->result('uncertain', $receipt->folder, $receipt->messageId, null);
        }
    }

    /** @return array{status: string, folder: string, messageId: string, uid: ?int} */
    private function result(string $status, string $folder, string $messageId, ?int $uid): array
    {
        return compact('status', 'folder', 'messageId', 'uid');
    }
}
