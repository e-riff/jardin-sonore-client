<?php

declare(strict_types=1);

namespace App\Application\Mcp;

use DateTimeImmutable;
use JsonException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

final readonly class WriteToolRegistry
{
    public function __construct(
        private MailReader $mailReader,
        private ProjectFollowupWriter $projectFollowupWriter,
        #[Autowire('%app.mcp.write_enabled%')] private bool $writeEnabled,
        private ?MailDraftWriter $mailDraftWriter = null,
        #[Autowire('%app.mcp.draft_write_enabled%')] private bool $draftWriteEnabled = false,
        private ?MailDraftEditor $mailDraftEditor = null,
        #[Autowire('%app.mcp.draft_edit_enabled%')] private bool $draftEditEnabled = false,
        private ?StandaloneMailDraftWriter $standaloneMailDraftWriter = null,
        #[Autowire('%app.mcp.draft_compose_enabled%')] private bool $draftComposeEnabled = false,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function definitions(): array
    {
        return [
            [
                'name' => 'create_mail_draft',
                'description' => 'After explicit user confirmation, create a standalone plain-text draft with the exact recipient, subject, and body supplied by the user. This does not reply to or inspect a source mail and never sends. Repeating identical inputs returns the existing draft.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'recipient' => ['type' => 'string', 'format' => 'email', 'minLength' => 3, 'maxLength' => 254],
                        'subject' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
                        'body' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 10000],
                    ],
                    'required' => ['recipient', 'subject', 'body'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'update_mail_draft',
                'description' => 'After a separate explicit user confirmation, replace the MCP-created reply draft linked to the verified source mail with this complete body. Read the draft first and pass its current draftMessageId and revision. The old draft remains if replacement cannot be verified. This never sends email.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'mailReference' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 1024],
                        'draftMessageId' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
                        'revision' => ['type' => 'string', 'minLength' => 64, 'maxLength' => 64],
                        'body' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 10000],
                    ],
                    'required' => ['mailReference', 'draftMessageId', 'revision', 'body'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => true, 'openWorldHint' => false],
            ],
            [
                'name' => 'save_mail_draft',
                'description' => 'After a separate explicit user confirmation, save one plain-text reply draft to the configured IMAP Drafts folder for an incoming message from another sender. Do not use this for self-sent digests or reminders. The recipient and subject are derived from the verified source mail. This never sends email; repeating the same source does not overwrite an edited draft.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'mailReference' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 1024],
                        'body' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 10000],
                    ],
                    'required' => ['mailReference', 'body'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'record_mail_summary',
                'description' => 'After explicit user confirmation, add a summary of a verified mail to one exact commercial project. Never infer projectId. Repeating the same project and mail returns the existing note.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'projectId' => ['type' => 'integer', 'minimum' => 1],
                        'mailReference' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 1024],
                        'summary' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 10000],
                    ],
                    'required' => ['projectId', 'mailReference', 'summary'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'create_project_reminder',
                'description' => 'After separate explicit user confirmation, add one dated open reminder to an exact commercial project and verified source mail. Repeating the same project and mail returns the existing reminder.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'projectId' => ['type' => 'integer', 'minimum' => 1],
                        'mailReference' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 1024],
                        'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
                        'dueOn' => ['type' => 'string', 'format' => 'date'],
                    ],
                    'required' => ['projectId', 'mailReference', 'title', 'dueOn'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
        ];
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function call(string $name, array $arguments): array
    {
        if ('save_mail_draft' === $name) {
            return $this->callDraft($arguments);
        }
        if ('update_mail_draft' === $name) {
            return $this->callDraftUpdate($arguments);
        }
        if ('create_mail_draft' === $name) {
            return $this->callStandaloneDraft($arguments);
        }
        if (!$this->writeEnabled) {
            return $this->error('Commercial writes are disabled.');
        }

        try {
            $allowed = match ($name) {
                'record_mail_summary' => ['projectId', 'mailReference', 'summary'],
                'create_project_reminder' => ['projectId', 'mailReference', 'title', 'dueOn'],
                default => throw new InvalidToolArguments('Unknown write tool.'),
            };
            if ([] !== array_diff(array_keys($arguments), $allowed)) {
                throw new InvalidToolArguments('Unexpected argument.');
            }
            $projectId = $arguments['projectId'] ?? null;
            if (!is_int($projectId) || 1 > $projectId) {
                throw new InvalidToolArguments('Invalid project ID.');
            }
            $reference = $this->text($arguments, 'mailReference', 1024);
            $summary = 'record_mail_summary' === $name ? $this->text($arguments, 'summary', 10000) : null;
            $title = 'create_project_reminder' === $name ? $this->text($arguments, 'title', 255) : null;
            $dueOn = 'create_project_reminder' === $name ? $this->date($arguments) : null;
            $source = $this->mailReader->source($reference);
            $result = 'record_mail_summary' === $name
                ? $this->projectFollowupWriter->recordSummary($projectId, $source, $summary)
                : $this->projectFollowupWriter->createReminder($projectId, $source, $title, $dueOn);
            $json = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return ['content' => [['type' => 'text', 'text' => $json]], 'isError' => false];
        } catch (InvalidToolArguments $exception) {
            return $this->error($exception->getMessage());
        } catch (JsonException) {
            return $this->error('The write operation returned invalid data.');
        } catch (Throwable) {
            return $this->error('The write operation failed.');
        }
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function callDraft(array $arguments): array
    {
        if (!$this->draftWriteEnabled || null === $this->mailDraftWriter) {
            return $this->error('Draft writes are disabled.');
        }
        try {
            if ([] !== array_diff(array_keys($arguments), ['mailReference', 'body'])) {
                throw new InvalidToolArguments('Unexpected argument.');
            }
            $reference = $this->text($arguments, 'mailReference', 1024);
            $body = $this->text($arguments, 'body', 10_000);
            $result = $this->mailDraftWriter->save($reference, $body);
            $json = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return ['content' => [['type' => 'text', 'text' => $json]], 'isError' => in_array($result['status'], ['uncertain', 'missing'], true)];
        } catch (InvalidToolArguments $exception) {
            return $this->error($exception->getMessage());
        } catch (MailReadException $exception) {
            return $this->error('Cannot reply to a message sent by this mailbox.' === $exception->getMessage()
                ? 'A reply draft can only be created for a message from another sender.'
                : 'The source message could not be used to create a reply draft.');
        } catch (Throwable) {
            return $this->error('Draft save failed.');
        }
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function callDraftUpdate(array $arguments): array
    {
        if (!$this->draftEditEnabled || null === $this->mailDraftEditor) {
            return $this->error('Draft editing is disabled.');
        }
        try {
            if ([] !== array_diff(array_keys($arguments), ['mailReference', 'draftMessageId', 'revision', 'body'])) {
                throw new InvalidToolArguments('Unexpected argument.');
            }
            $reference = $this->text($arguments, 'mailReference', 1024);
            $draftMessageId = $this->text($arguments, 'draftMessageId', 255);
            $revision = $this->text($arguments, 'revision', 64);
            $body = $this->draftBody($arguments, 'body', 10_000);
            $result = $this->mailDraftEditor->update($reference, $draftMessageId, $revision, $body);
            $json = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return ['content' => [['type' => 'text', 'text' => $json]], 'isError' => !in_array($result['status'] ?? null, ['saved'], true)];
        } catch (InvalidToolArguments $exception) {
            return $this->error($exception->getMessage());
        } catch (JsonException) {
            return $this->error('The draft update returned invalid data.');
        } catch (Throwable) {
            return $this->error('Draft update failed.');
        }
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function callStandaloneDraft(array $arguments): array
    {
        if (!$this->draftComposeEnabled || null === $this->standaloneMailDraftWriter) {
            return $this->error('Standalone draft creation is disabled.');
        }
        try {
            if ([] !== array_diff(array_keys($arguments), ['recipient', 'subject', 'body'])) {
                throw new InvalidToolArguments('Unexpected argument.');
            }
            $recipient = $this->text($arguments, 'recipient', 254);
            if (false === filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidToolArguments('Invalid draft recipient.');
            }
            $subject = $this->text($arguments, 'subject', 255);
            $body = $this->draftBody($arguments, 'body', 10_000);
            $result = $this->standaloneMailDraftWriter->create($recipient, $subject, $body);
            $json = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return ['content' => [['type' => 'text', 'text' => $json]], 'isError' => in_array($result['status'], ['uncertain'], true)];
        } catch (InvalidToolArguments $exception) {
            return $this->error($exception->getMessage());
        } catch (JsonException) {
            return $this->error('The standalone draft result was invalid.');
        } catch (Throwable) {
            return $this->error('Standalone draft creation failed.');
        }
    }

    /** @param array<string, mixed> $arguments */
    private function text(array $arguments, string $name, int $maxLength): string
    {
        $value = $arguments[$name] ?? null;
        if (!is_string($value) || '' === trim($value) || mb_strlen($value) > $maxLength || 1 === preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            throw new InvalidToolArguments("Invalid {$name}.");
        }

        return trim($value);
    }

    /** @param array<string, mixed> $arguments */
    private function draftBody(array $arguments, string $name, int $maxLength): string
    {
        $value = $arguments[$name] ?? null;
        if (!is_string($value) || '' === trim($value) || mb_strlen($value) > $maxLength || 1 === preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            throw new InvalidToolArguments("Invalid {$name}.");
        }

        return $value;
    }

    /** @param array<string, mixed> $arguments */
    private function date(array $arguments): DateTimeImmutable
    {
        $value = $arguments['dueOn'] ?? null;
        if (!is_string($value) || 1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            throw new InvalidToolArguments('Invalid due date.');
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw new InvalidToolArguments('Invalid due date.');
        }

        return $date;
    }

    /** @return array<string, mixed> */
    private function error(string $message): array
    {
        return ['content' => [['type' => 'text', 'text' => $message]], 'isError' => true];
    }
}
