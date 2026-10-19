<?php

declare(strict_types=1);

namespace App\Application\Mcp;

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

    /** @return array{status: string, folder: string, messageId: string, uid: ?int, to: string, subject: string} */
    public function create(string $recipient, string $subject, string $body): array
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
        if (false === filter_var($this->mailboxAddress, FILTER_VALIDATE_EMAIL) || '' === trim($this->draftsFolder)) {
            throw new InvalidArgumentException('Invalid mailbox configuration.');
        }

        $domain = substr(strrchr($this->mailboxAddress, '@'), 1);
        $identity = hash('sha256', mb_strtolower($recipient) . "\0" . $subject . "\0" . $body);
        $messageId = '<mcp-' . substr($identity, 0, 56) . '@' . $domain . '>';

        try {
            $uid = $this->draftStore->findByMessageId($this->draftsFolder, $messageId);
            if (null !== $uid) {
                return $this->result('already_exists', $messageId, $uid, $recipient, $subject);
            }

            $mime = $this->mimeBuilder->build($recipient, $subject, $body, $messageId, $this->mailboxAddress, new DateTimeImmutable());
            $this->draftStore->append($this->draftsFolder, $mime);
            $uid = $this->draftStore->findByMessageId($this->draftsFolder, $messageId);
            if (null === $uid) {
                return $this->result('uncertain', $messageId, null, $recipient, $subject);
            }

            return $this->result('saved', $messageId, $uid, $recipient, $subject);
        } catch (Throwable) {
            return $this->result('uncertain', $messageId, null, $recipient, $subject);
        }
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
