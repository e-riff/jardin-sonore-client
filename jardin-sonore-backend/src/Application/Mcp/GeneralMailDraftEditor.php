<?php

declare(strict_types=1);

namespace App\Application\Mcp;

use App\Infrastructure\Mail\DraftHtmlBody;
use App\Infrastructure\Mail\DraftMimeBuilder;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

final readonly class GeneralMailDraftEditor
{
    public function __construct(
        private MailDraftStore $draftStore,
        private DraftMimeBuilder $mimeBuilder,
        #[Autowire('%app.mcp.imap_username%')] private string $mailboxAddress,
        #[Autowire('%app.mcp.imap_drafts_folder%')] private string $draftsFolder,
    ) {
    }

    /** @return array<string, mixed> */
    public function search(?string $query, int $limit = 10): array
    {
        if (1 > $limit || 25 < $limit || (null !== $query && (200 < mb_strlen($query) || 1 === preg_match('/[\x00-\x1F\x7F]/', $query)))) {
            throw new InvalidArgumentException('Invalid draft search.');
        }

        return ['drafts' => $this->draftStore->search($this->draftsFolder, $query, $limit)];
    }

    /** @return array<string, mixed> */
    public function read(string $messageId): array
    {
        $this->assertIdentifier($messageId);

        return $this->draftStore->getByMessageId($this->draftsFolder, $messageId)?->result() ?? ['status' => 'missing', 'nextAction' => 'search_mail_drafts'];
    }

    /** @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    public function update(string $messageId, string $revision, array $changes): array
    {
        $this->assertIdentifier($messageId);
        if ([] === $changes || 1 !== preg_match('/^[a-f0-9]{64}$/D', $revision)
            || [] !== array_diff(array_keys($changes), ['body', 'htmlBody', 'subject', 'recipient', 'recipients', 'cc', 'bcc', 'attachments', 'removeAttachmentIds'])) {
            throw new InvalidArgumentException('Invalid draft update.');
        }
        $current = $this->draftStore->getByMessageId($this->draftsFolder, $messageId);
        if (null === $current) {
            return ['status' => 'missing', 'nextAction' => 'search_mail_drafts'];
        }
        if (!hash_equals($current->snapshotRevision(), $revision)) {
            return ['status' => 'conflict', 'nextAction' => 'read_standalone_mail_draft'];
        }
        $desired = $this->applyChanges($current, $changes);
        $newId = '<mcp-standalone-edit-' . hash('sha256', $messageId . "\0" . $revision . "\0" . $desired->snapshotRevision()) . '@' . substr(strrchr($this->mailboxAddress, '@'), 1) . '>';
        $desired = new MailDraft(0, $newId, $desired->subject, $desired->recipient, $desired->body, $desired->htmlBody, $desired->additionalRecipients, $desired->cc, $desired->bcc, $desired->attachments, $desired->inReplyTo, $desired->references);
        try {
            $replacement = $this->draftStore->getByMessageId($this->draftsFolder, $newId);
            if (null === $replacement) {
                $this->draftStore->append($this->draftsFolder, $this->mimeBuilder->build($desired, $this->mailboxAddress));
                $replacement = $this->draftStore->getByMessageId($this->draftsFolder, $newId);
            }
            if (null === $replacement || !hash_equals($desired->snapshotRevision(), $replacement->snapshotRevision())) {
                return ['status' => 'uncertain'];
            }
            $latest = $this->draftStore->getByMessageId($this->draftsFolder, $messageId);
            if (null === $latest || $latest->uid !== $current->uid || !hash_equals($revision, $latest->snapshotRevision())) {
                return ['status' => 'conflict', 'replacementMessageId' => $newId];
            }
            if (!$this->draftStore->deleteByMessageId($this->draftsFolder, $messageId, $current->uid)) {
                return ['status' => 'uncertain', 'replacementVerified' => true, 'previousDraftRemoved' => false, 'replacementMessageId' => $newId];
            }

            return $replacement->result();
        } catch (Throwable) {
            return ['status' => 'uncertain'];
        }
    }

    /** @param array<string, mixed> $changes */
    public function applyChanges(MailDraft $current, array $changes): MailDraft
    {
        $body = array_key_exists('body', $changes) ? $this->text($changes['body'], 10_000) : $current->body;
        $subject = array_key_exists('subject', $changes) ? $this->text($changes['subject'], 255, true) : $current->subject;
        $html = array_key_exists('htmlBody', $changes) ? (null === $changes['htmlBody'] ? null : DraftHtmlBody::sanitize($this->text($changes['htmlBody'], 20_000))) : $current->htmlBody;
        if (array_key_exists('recipient', $changes) && array_key_exists('recipients', $changes)) {
            throw new InvalidArgumentException('Use recipient or recipients, not both.');
        }
        $recipients = $this->addresses(array_key_exists('recipients', $changes) ? $changes['recipients'] : (array_key_exists('recipient', $changes) ? [$changes['recipient']] : array_values(array_filter([$current->recipient, ...$current->additionalRecipients]))));
        $cc = $this->addresses(array_key_exists('cc', $changes) ? $changes['cc'] : $current->cc);
        $bcc = $this->addresses(array_key_exists('bcc', $changes) ? $changes['bcc'] : $current->bcc);
        $removed = $changes['removeAttachmentIds'] ?? [];
        $added = $changes['attachments'] ?? [];
        if (!is_array($removed) || !array_is_list($removed) || !is_array($added) || !array_is_list($added) || MailDraftAttachment::MAX_COUNT < count($added)) {
            throw new InvalidArgumentException('Invalid attachment list.');
        }
        $knownIds = array_map(static fn (MailDraftAttachment $attachment): string => $attachment->metadata()['id'], $current->attachments);
        $encodedBytes = 0;
        foreach ($added as $payload) {
            if (!is_array($payload) || !isset($payload['base64']) || !is_string($payload['base64'])) {
                throw new InvalidArgumentException('Invalid attachment.');
            }
            $encodedBytes += strlen($payload['base64']);
            if (4 * (int) ceil(MailDraftAttachment::MAX_TOTAL_BYTES / 3) + 4 * MailDraftAttachment::MAX_COUNT < $encodedBytes) {
                throw new InvalidArgumentException('Encoded attachments exceed the size limit.');
            }
        }
        foreach ($removed as $id) {
            if (!is_string($id) || !in_array($id, $knownIds, true)) {
                throw new InvalidArgumentException('Unknown attachment identifier; read the draft again.');
            }
        }
        $attachments = array_values(array_filter($current->attachments, static fn (MailDraftAttachment $attachment): bool => !in_array($attachment->metadata()['id'], $removed, true)));
        foreach ($added as $payload) {
            if (!is_array($payload)) {
                throw new InvalidArgumentException('Invalid attachment.');
            }
            $attachments[] = MailDraftAttachment::fromPayload($payload);
            MailDraftAttachment::assertLimits($attachments);
        }
        MailDraftAttachment::assertLimits($attachments);

        return new MailDraft($current->uid, $current->messageId, $subject, $recipients[0] ?? '', $body, $html, array_slice($recipients, 1), $cc, $bcc, $attachments, $current->inReplyTo, $current->references);
    }

    /** @return list<string> */
    private function addresses(mixed $addresses): array
    {
        if (!is_array($addresses) || !array_is_list($addresses) || 50 < count($addresses)) {
            throw new InvalidArgumentException('Invalid draft recipients.');
        }
        foreach ($addresses as $address) {
            if (!is_string($address) || 254 < strlen($address) || false === filter_var($address, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Invalid draft recipient.');
            }
        }

        return $addresses;
    }

    private function text(mixed $value, int $max, bool $header = false): string
    {
        if (!is_string($value) || '' === trim($value) || $max < mb_strlen($value)
            || 1 === preg_match($header ? '/[\x00-\x1F\x7F]/' : '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            throw new InvalidArgumentException('Invalid draft text or subject.');
        }

        return $value;
    }

    private function assertIdentifier(string $messageId): void
    {
        if (false === filter_var($this->mailboxAddress, FILTER_VALIDATE_EMAIL) || '' === trim($this->draftsFolder)
            || (1 !== preg_match('/^<[^<>\s"\\\\]{1,250}>$/D', $messageId) && 1 !== preg_match('/^draft:[1-9][0-9]{0,9}:[1-9][0-9]{0,9}$/D', $messageId))) {
            throw new InvalidArgumentException('Invalid draft identifier or mailbox configuration.');
        }
    }
}
