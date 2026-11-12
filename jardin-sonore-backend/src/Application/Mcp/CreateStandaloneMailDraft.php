<?php

declare(strict_types=1);

namespace App\Application\Mcp;

use App\Infrastructure\Mail\DraftHtmlBody;
use App\Infrastructure\Mail\DraftMimeBuilder;
use App\Infrastructure\Mail\StandaloneDraftMimeBuilder;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

final readonly class CreateStandaloneMailDraft implements StandaloneMailDraftWriter
{
    public function __construct(
        private MailDraftStore $draftStore,
        private StandaloneDraftMimeBuilder $mimeBuilder,
        #[Autowire('%app.mcp.imap_username%')] private string $mailboxAddress,
        #[Autowire('%app.mcp.imap_drafts_folder%')] private string $draftsFolder,
    ) {
    }

    /** @param list<array<string, mixed>> $attachments
     * @return array<string, mixed>
     */
    public function create(string $recipient, string $subject, string $body, ?string $htmlBody = null, array $attachments = []): array
    {
        $recipient = trim($recipient);
        $subject = trim($subject);
        if (false === filter_var($recipient, FILTER_VALIDATE_EMAIL) || 254 < strlen($recipient) || 1 === preg_match('/[\r\n]/', $recipient)) {
            throw new InvalidArgumentException('Invalid draft recipient.');
        }
        if ('' === $subject || 255 < mb_strlen($subject) || 1 === preg_match('/[\x00-\x1F\x7F]/', $subject)) {
            throw new InvalidArgumentException('Invalid draft subject.');
        }
        if ('' === trim($body) || 10_000 < mb_strlen($body) || 1 === preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $body)) {
            throw new InvalidArgumentException('Invalid draft body.');
        }
        if (null !== $htmlBody) {
            $htmlBody = DraftHtmlBody::sanitize($htmlBody);
        }
        if (false === filter_var($this->mailboxAddress, FILTER_VALIDATE_EMAIL) || '' === trim($this->draftsFolder)) {
            throw new InvalidArgumentException('Invalid mailbox configuration.');
        }

        $domain = substr(strrchr($this->mailboxAddress, '@'), 1);
        $draftMimeBuilder = new DraftMimeBuilder();
        $draft = (new GeneralMailDraftEditor($this->draftStore, $draftMimeBuilder, $this->mailboxAddress, $this->draftsFolder))->applyChanges(
            new MailDraft(0, '<pending@' . $domain . '>', $subject, $recipient, $body, $htmlBody),
            ['attachments' => $attachments],
        );
        $identity = hash('sha256', mb_strtolower($recipient) . "\0" . $subject . "\0" . $body . (null === $htmlBody ? '' : "\0" . $htmlBody));
        if ([] !== $attachments) {
            $identity = $draft->snapshotRevision();
        }
        $messageId = '<mcp-' . substr($identity, 0, 56) . '@' . $domain . '>';

        try {
            $uid = $this->draftStore->findByMessageId($this->draftsFolder, $messageId);
            if (null !== $uid) {
                return $this->storedResult('already_exists', $messageId, $draft);
            }

            $mime = $this->mimeBuilder->build($recipient, $subject, $body, $messageId, $this->mailboxAddress, new DateTimeImmutable(), $htmlBody);
            if ([] !== $attachments) {
                $mime = $draftMimeBuilder->build(new MailDraft(0, $messageId, $subject, $recipient, $body, $htmlBody, attachments: $draft->attachments), $this->mailboxAddress);
            }
            $this->draftStore->append($this->draftsFolder, $mime);
            $uid = $this->draftStore->findByMessageId($this->draftsFolder, $messageId);
            if (null === $uid) {
                return $this->result('uncertain', $messageId, null, $recipient, $subject);
            }

            return $this->storedResult('saved', $messageId, $draft);
        } catch (Throwable) {
            return $this->result('uncertain', $messageId, null, $recipient, $subject);
        }
    }

    /** @return array<string, mixed> */
    private function storedResult(string $status, string $messageId, MailDraft $expectedDraft): array
    {
        $storedDraft = $this->draftStore->getByMessageId($this->draftsFolder, $messageId);
        if (null === $storedDraft) {
            return ['status' => 'uncertain', 'messageId' => $messageId, 'nextAction' => 'read_standalone_mail_draft'];
        }
        if ('saved' === $status && !hash_equals($expectedDraft->snapshotRevision(), $storedDraft->snapshotRevision())) {
            $status = 'verification_failed';
        }

        return array_replace($storedDraft->result(), ['status' => $status, 'folder' => $this->draftsFolder, 'uid' => $storedDraft->uid]);
    }

    /** @return array{status: string, folder: string, messageId: string, uid: ?int, to: string, subject: string} */
    private function result(string $status, string $messageId, ?int $uid, string $recipient, string $subject): array
    {
        return [
            'status' => $status,
            'folder' => $this->draftsFolder,
            'messageId' => $messageId,
            'uid' => $uid,
            'to' => $recipient,
            'subject' => $subject,
        ];
    }
}
