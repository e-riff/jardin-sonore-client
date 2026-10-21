<?php

declare(strict_types=1);

namespace App\Application\Mcp;

use App\Infrastructure\Mail\DraftHtmlBody;
use App\Infrastructure\Mail\StandaloneDraftMimeBuilder;
use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

final readonly class EditStandaloneMailDraft implements StandaloneMailDraftEditor
{
    public function __construct(
        private MailDraftStore $draftStore,
        private StandaloneDraftMimeBuilder $mimeBuilder,
        #[Autowire('%app.mcp.imap_username%')] private string $mailboxAddress,
        #[Autowire('%app.mcp.imap_drafts_folder%')] private string $draftsFolder,
    ) {
    }

    public function read(string $draftMessageId): array
    {
        $this->assertOwnedMessageId($draftMessageId);

        try {
            $draft = $this->draftStore->getByMessageId($this->draftsFolder, $draftMessageId);

            return null === $draft ? ['status' => 'missing'] : $this->result($draft);
        } catch (Throwable) {
            return ['status' => 'uncertain'];
        }
    }

    public function update(string $draftMessageId, string $expectedRevision, string $body, ?string $htmlBody = null): array
    {
        $this->assertOwnedMessageId($draftMessageId);
        if (1 !== preg_match('/^[a-f0-9]{64}$/D', $expectedRevision) || '' === trim($body) || 10_000 < mb_strlen($body)
            || 1 === preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $body)) {
            throw new InvalidArgumentException('Invalid standalone draft update.');
        }
        if (null !== $htmlBody) {
            $htmlBody = DraftHtmlBody::sanitize($htmlBody);
        }

        $domain = substr(strrchr($this->mailboxAddress, '@'), 1);
        $newMessageId = '<mcp-standalone-edit-' . hash('sha256', $draftMessageId . "\0" . $body . (null === $htmlBody ? '' : "\0" . $htmlBody)) . '@' . $domain . '>';

        try {
            $currentDraft = $this->draftStore->getByMessageId($this->draftsFolder, $draftMessageId);
            $newDraft = $this->draftStore->getByMessageId($this->draftsFolder, $newMessageId);
            if (null === $currentDraft) {
                return null !== $newDraft && hash_equals(MailDraft::contentRevision($body, $htmlBody), $newDraft->revision())
                    ? $this->result($newDraft)
                    : ['status' => 'missing'];
            }
            if (!hash_equals($expectedRevision, $currentDraft->revision())) {
                return ['status' => 'conflict'];
            }
            if (null === $currentDraft->subject || '' === trim($currentDraft->subject) || false === filter_var($currentDraft->recipient, FILTER_VALIDATE_EMAIL)) {
                return ['status' => 'unavailable'];
            }

            if (null === $newDraft) {
                $mime = $this->mimeBuilder->build($currentDraft->recipient, $currentDraft->subject, $body, $newMessageId, $this->mailboxAddress, new DateTimeImmutable(), $htmlBody);
                $this->draftStore->append($this->draftsFolder, $mime);
                $newDraft = $this->draftStore->getByMessageId($this->draftsFolder, $newMessageId);
            }
            if (null === $newDraft || !hash_equals(MailDraft::contentRevision($body, $htmlBody), $newDraft->revision())
                || $newDraft->recipient !== $currentDraft->recipient || $newDraft->subject !== $currentDraft->subject) {
                return ['status' => 'uncertain'];
            }

            $latestDraft = $this->draftStore->getByMessageId($this->draftsFolder, $draftMessageId);
            if (null === $latestDraft || $latestDraft->uid !== $currentDraft->uid || !hash_equals($expectedRevision, $latestDraft->revision())) {
                return ['status' => 'conflict'];
            }
            if (!$this->draftStore->deleteByMessageId($this->draftsFolder, $draftMessageId, $currentDraft->uid)) {
                return ['status' => 'uncertain', 'replacementVerified' => true, 'previousDraftRemoved' => false];
            }

            return $this->result($newDraft);
        } catch (Throwable) {
            return ['status' => 'uncertain'];
        }
    }

    private function assertOwnedMessageId(string $draftMessageId): void
    {
        if (false === filter_var($this->mailboxAddress, FILTER_VALIDATE_EMAIL) || '' === trim($this->draftsFolder)) {
            throw new InvalidArgumentException('Invalid mailbox configuration.');
        }
        $domain = preg_quote(substr(strrchr($this->mailboxAddress, '@'), 1), '/');
        if (1 !== preg_match('/^<mcp-(?:[a-f0-9]{56}|standalone-edit-[a-f0-9]{64})@' . $domain . '>$/D', $draftMessageId)) {
            throw new InvalidArgumentException('Invalid standalone draft identifier.');
        }
    }

    /** @return array<string, mixed> */
    private function result(MailDraft $draft): array
    {
        return [
            'status' => 'saved',
            'draftMessageId' => $draft->messageId,
            'revision' => $draft->revision(),
            'to' => $draft->recipient,
            'subject' => $draft->subject,
            'body' => $draft->body,
            'htmlBody' => $draft->htmlBody,
        ];
    }
}
