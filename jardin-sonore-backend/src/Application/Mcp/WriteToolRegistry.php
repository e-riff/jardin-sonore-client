<?php

declare(strict_types=1);

namespace App\Application\Mcp;

use App\Infrastructure\Google\GoogleCalendarConflictException;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
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
        private ?MailingDraftManager $mailingDraftManager = null,
        #[Autowire('%app.mcp.mailing_write_enabled%')] private bool $mailingWriteEnabled = false,
        private ?ProjectScheduleManager $projectScheduleManager = null,
        #[Autowire('%app.mcp.project_schedule_write_enabled%')] private bool $projectScheduleWriteEnabled = false,
        private ?StandaloneMailDraftEditor $standaloneMailDraftEditor = null,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function definitions(): array
    {
        return [
            [
                'name' => 'create_mail_draft',
                'description' => 'After explicit user confirmation, create a standalone draft with the exact recipient, subject, text body, and optional safe HTML body supplied by the user. This never sends. Keep the returned messageId for later reading or editing. Repeating identical inputs returns the existing draft.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'recipient' => ['type' => 'string', 'format' => 'email', 'minLength' => 3, 'maxLength' => 254],
                        'subject' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
                        'body' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 10000],
                        'htmlBody' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 20000],
                    ],
                    'required' => ['recipient', 'subject', 'body'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'update_mail_draft',
                'description' => 'After a separate explicit user confirmation, replace the MCP-created reply draft linked to the verified source mail with a complete text body and optional safe HTML body. Read it first and pass its current draftMessageId and revision. The old draft remains if replacement cannot be verified. This never sends email.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'mailReference' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 1024],
                        'draftMessageId' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
                        'revision' => ['type' => 'string', 'minLength' => 64, 'maxLength' => 64],
                        'body' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 10000],
                        'htmlBody' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 20000],
                    ],
                    'required' => ['mailReference', 'draftMessageId', 'revision', 'body'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => true, 'openWorldHint' => false],
            ],
            [
                'name' => 'update_standalone_mail_draft',
                'description' => 'After a separate explicit user confirmation, replace a standalone draft created by create_mail_draft. Read it with read_standalone_mail_draft first, then supply its current draftMessageId and revision plus the complete new text body and optional safe HTML body. Recipient and subject stay unchanged. This never sends email.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'draftMessageId' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
                        'revision' => ['type' => 'string', 'minLength' => 64, 'maxLength' => 64],
                        'body' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 10000],
                        'htmlBody' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 20000],
                    ],
                    'required' => ['draftMessageId', 'revision', 'body'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => true, 'openWorldHint' => false],
            ],
            [
                'name' => 'save_mail_draft',
                'description' => 'After a separate explicit user confirmation, save one reply draft with a text body and optional safe HTML body to the configured IMAP Drafts folder. Do not use this for self-sent digests or reminders. The recipient and subject come from the verified source mail. This never sends; repeating the same source does not overwrite an edited draft.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'mailReference' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 1024],
                        'body' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 10000],
                        'htmlBody' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 20000],
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
                        'dueTime' => ['type' => ['string', 'null'], 'pattern' => '^([01][0-9]|2[0-3]):[0-5][0-9]$'],
                    ],
                    'required' => ['projectId', 'mailReference', 'title', 'dueOn'],
                    'additionalProperties' => false,
                ],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'create_mailing_draft',
                'description' => 'After explicit confirmation in the client, create a mailing campaign draft. Supply a stable requestId so retries return the same draft. Create campaign-specific recommendations with title, text, tag and optional link; images are nullable and new recommendations start without one. Tell the user which new recommendations need an image added in the backoffice. Never sends, schedules, or test-sends a mailing.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'requestId' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 100],
                    'internalTitle' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
                    'emailSubject' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
                    'publicTitle' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
                    'preheader' => ['type' => ['string', 'null'], 'maxLength' => 255],
                    'subtitle' => ['type' => ['string', 'null'], 'maxLength' => 255],
                    'introBlocks' => ['type' => 'array', 'maxItems' => 20, 'items' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string', 'maxLength' => 64], 'kind' => ['const' => 'paragraph'], 'text' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 10000]], 'required' => ['id', 'kind', 'text'], 'additionalProperties' => false]],
                    'callToActionLabel' => ['type' => ['string', 'null'], 'maxLength' => 255],
                    'callToActionUrl' => ['type' => ['string', 'null'], 'maxLength' => 2048],
                    'audienceMaskUuid' => ['type' => 'string', 'format' => 'uuid'],
                    'recommendations' => ['type' => 'array', 'maxItems' => 10, 'items' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255], 'tag' => ['type' => ['string', 'null'], 'maxLength' => 255], 'text' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 10000], 'url' => ['type' => ['string', 'null'], 'maxLength' => 2048], 'linkLabel' => ['type' => ['string', 'null'], 'maxLength' => 255], 'imagePath' => ['type' => ['string', 'null']], 'position' => ['type' => 'integer'], 'active' => ['type' => 'boolean']], 'required' => ['title', 'text'], 'additionalProperties' => false]],
                ], 'required' => ['requestId', 'internalTitle', 'emailSubject', 'publicTitle', 'introBlocks'], 'additionalProperties' => false],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            [
                'name' => 'update_mailing_draft',
                'description' => 'After explicit confirmation in the client, update a mailing by exact UUID and current revision from get_mailing_campaign. Only campaigns whose delivery has not started can be edited. Omitted mailing fields stay unchanged. The recommendations array is the complete ordered set: include existing recommendationUuid values to edit them, omit one to remove it, or omit the UUID to create a campaign-specific recommendation. New recommendations have no image; tell the user which ones need an image added in the backoffice. Never sends, schedules, or test-sends a mailing.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'campaignUuid' => ['type' => 'string', 'format' => 'uuid'],
                    'revision' => ['type' => 'string', 'minLength' => 64, 'maxLength' => 64],
                    'internalTitle' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
                    'emailSubject' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
                    'publicTitle' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
                    'preheader' => ['type' => ['string', 'null'], 'maxLength' => 255],
                    'subtitle' => ['type' => ['string', 'null'], 'maxLength' => 255],
                    'mainText' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 20000],
                    'introBlocks' => ['type' => 'array', 'maxItems' => 20, 'items' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string', 'maxLength' => 64], 'kind' => ['enum' => ['paragraph', 'image', 'split']], 'text' => ['type' => 'string', 'maxLength' => 10000], 'imagePath' => ['type' => 'string'], 'imageAlt' => ['type' => 'string'], 'imageDecorative' => ['type' => 'boolean'], 'imageSide' => ['enum' => ['left', 'right']]], 'required' => ['id', 'kind'], 'additionalProperties' => false]],
                    'callToActionLabel' => ['type' => ['string', 'null'], 'maxLength' => 255],
                    'callToActionUrl' => ['type' => ['string', 'null'], 'maxLength' => 2048],
                    'audienceMaskUuid' => ['type' => ['string', 'null'], 'format' => 'uuid'],
                    'recommendations' => ['type' => 'array', 'maxItems' => 10, 'items' => ['type' => 'object', 'properties' => ['recommendationUuid' => ['type' => 'string', 'format' => 'uuid'], 'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255], 'tag' => ['type' => ['string', 'null'], 'maxLength' => 255], 'text' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 10000], 'url' => ['type' => ['string', 'null'], 'maxLength' => 2048], 'linkLabel' => ['type' => ['string', 'null'], 'maxLength' => 255], 'imagePath' => ['type' => ['string', 'null']], 'position' => ['type' => 'integer'], 'active' => ['type' => 'boolean']], 'required' => ['title', 'text'], 'additionalProperties' => false]],
                ], 'required' => ['campaignUuid', 'revision'], 'additionalProperties' => false],
                'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false],
            ],
            ...$this->scheduleDefinitions(),
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
        if ('update_standalone_mail_draft' === $name) {
            return $this->callStandaloneDraftUpdate($arguments);
        }
        if (in_array($name, ['create_mailing_draft', 'update_mailing_draft'], true)) {
            return $this->callMailingDraft($name, $arguments);
        }
        if (in_array($name, ['create_project_task', 'update_project_task', 'complete_project_task', 'create_project_appointment', 'update_project_appointment', 'cancel_project_appointment', 'set_project_appointment_summary'], true)) {
            return $this->callSchedule($name, $arguments);
        }
        if (!$this->writeEnabled) {
            return $this->error('Commercial writes are disabled.');
        }

        try {
            $allowed = match ($name) {
                'record_mail_summary' => ['projectId', 'mailReference', 'summary'],
                'create_project_reminder' => ['projectId', 'mailReference', 'title', 'dueOn', 'dueTime'],
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
            $dueTime = $this->optionalDueTime($arguments);
            $source = $this->mailReader->source($reference);
            $result = 'record_mail_summary' === $name
                ? $this->projectFollowupWriter->recordSummary($projectId, $source, $summary)
                : $this->projectFollowupWriter->createReminder($projectId, $source, $title, $dueOn, $dueTime);
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

    /** @return list<array<string, mixed>> */
    private function scheduleDefinitions(): array
    {
        $projectId = ['type' => 'integer', 'minimum' => 1];
        $recordId = ['type' => 'integer', 'minimum' => 1];
        $revision = ['type' => 'integer', 'minimum' => 1];
        $taskRevision = ['type' => 'string', 'minLength' => 64, 'maxLength' => 64];
        $title = ['type' => 'string', 'minLength' => 1, 'maxLength' => 255];
        $dueOn = ['type' => 'string', 'format' => 'date'];
        $dueTime = ['type' => ['string', 'null'], 'pattern' => '^([01][0-9]|2[0-3]):[0-5][0-9]$'];
        $dateTime = ['type' => 'string', 'pattern' => '^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}$'];
        $location = ['type' => ['string', 'null'], 'maxLength' => 1000];
        $annotation = ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false];
        $confirmation = 'After a separate explicit user confirmation, ';

        return [
            ['name' => 'create_project_task', 'description' => $confirmation . 'create a dated task in an exact dossier. The optional time is indicative and does not reserve Calendar.', 'inputSchema' => ['type' => 'object', 'properties' => ['projectId' => $projectId, 'title' => $title, 'dueOn' => $dueOn, 'dueTime' => $dueTime], 'required' => ['projectId', 'title', 'dueOn'], 'additionalProperties' => false], 'annotations' => $annotation],
            ['name' => 'update_project_task', 'description' => $confirmation . 'update one task by exact dossier and task IDs. Use the revision from get_project_context; omitted dueTime retains its value.', 'inputSchema' => ['type' => 'object', 'properties' => ['projectId' => $projectId, 'taskId' => $recordId, 'revision' => $taskRevision, 'title' => $title, 'dueOn' => $dueOn, 'dueTime' => $dueTime], 'required' => ['projectId', 'taskId', 'revision', 'title', 'dueOn'], 'additionalProperties' => false], 'annotations' => $annotation],
            ['name' => 'complete_project_task', 'description' => $confirmation . 'complete one task by exact dossier and task IDs with the current revision.', 'inputSchema' => ['type' => 'object', 'properties' => ['projectId' => $projectId, 'taskId' => $recordId, 'revision' => $taskRevision, 'note' => ['type' => ['string', 'null'], 'maxLength' => 10000]], 'required' => ['projectId', 'taskId', 'revision'], 'additionalProperties' => false], 'annotations' => $annotation],
            ['name' => 'create_project_appointment', 'description' => $confirmation . 'reserve one personal Google Calendar appointment linked to an exact dossier. Supply a stable 32-character hexadecimal requestId for safe retries. No contact is invited.', 'inputSchema' => ['type' => 'object', 'properties' => ['projectId' => $projectId, 'requestId' => ['type' => 'string', 'pattern' => '^[0-9a-f]{32}$'], 'title' => $title, 'startsAt' => $dateTime, 'endsAt' => $dateTime, 'location' => $location], 'required' => ['projectId', 'requestId', 'title', 'startsAt', 'endsAt'], 'additionalProperties' => false], 'annotations' => $annotation],
            ['name' => 'update_project_appointment', 'description' => $confirmation . 'update one appointment by exact dossier and appointment IDs with the current revision from get_project_appointment. No contact is invited.', 'inputSchema' => ['type' => 'object', 'properties' => ['projectId' => $projectId, 'appointmentId' => $recordId, 'revision' => $revision, 'title' => $title, 'startsAt' => $dateTime, 'endsAt' => $dateTime, 'location' => $location], 'required' => ['projectId', 'appointmentId', 'revision', 'title', 'startsAt', 'endsAt'], 'additionalProperties' => false], 'annotations' => $annotation],
            ['name' => 'cancel_project_appointment', 'description' => $confirmation . 'remove one appointment from Google Calendar by exact IDs and revision, retaining its dossier history.', 'inputSchema' => ['type' => 'object', 'properties' => ['projectId' => $projectId, 'appointmentId' => $recordId, 'revision' => $revision], 'required' => ['projectId', 'appointmentId', 'revision'], 'additionalProperties' => false], 'annotations' => ['readOnlyHint' => false, 'destructiveHint' => true, 'openWorldHint' => false]],
            ['name' => 'set_project_appointment_summary', 'description' => $confirmation . 'set or correct the summary of one appointment by exact IDs and revision.', 'inputSchema' => ['type' => 'object', 'properties' => ['projectId' => $projectId, 'appointmentId' => $recordId, 'revision' => $revision, 'summary' => ['type' => ['string', 'null'], 'maxLength' => 10000]], 'required' => ['projectId', 'appointmentId', 'revision'], 'additionalProperties' => false], 'annotations' => $annotation],
        ];
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function callSchedule(string $name, array $arguments): array
    {
        if (!$this->projectScheduleWriteEnabled || null === $this->projectScheduleManager) {
            return $this->error('Project schedule writes are disabled.');
        }
        try {
            $definition = null;
            foreach ($this->scheduleDefinitions() as $candidate) {
                if ($candidate['name'] === $name) {
                    $definition = $candidate;
                    break;
                }
            }
            if (null === $definition) {
                throw new InvalidToolArguments('Unknown schedule operation.');
            }
            $schema = $definition['inputSchema'];
            if ([] !== array_diff(array_keys($arguments), array_keys($schema['properties']))) {
                throw new InvalidToolArguments('Unexpected argument.');
            }
            foreach ($schema['required'] as $required) {
                if (!array_key_exists($required, $arguments)) {
                    throw new InvalidToolArguments("Missing {$required}.");
                }
            }
            $result = $this->projectScheduleManager->execute($name, $arguments);
            $json = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return ['content' => [['type' => 'text', 'text' => $json]], 'isError' => false];
        } catch (GoogleCalendarConflictException) {
            return $this->error('The appointment changed; read it again before retrying.');
        } catch (DomainException|InvalidToolArguments $exception) {
            return $this->error($exception->getMessage());
        } catch (Throwable) {
            return $this->error('The project schedule operation failed.');
        }
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function callMailingDraft(string $name, array $arguments): array
    {
        if (!$this->mailingWriteEnabled || null === $this->mailingDraftManager) {
            return $this->error('Mailing draft writes are disabled.');
        }
        try {
            $allowed = 'create_mailing_draft' === $name
                ? ['requestId', 'internalTitle', 'emailSubject', 'publicTitle', 'mainText', 'introBlocks', 'preheader', 'subtitle', 'callToActionLabel', 'callToActionUrl', 'audienceMaskUuid', 'recommendations']
                : ['campaignUuid', 'revision', 'internalTitle', 'emailSubject', 'publicTitle', 'mainText', 'introBlocks', 'preheader', 'subtitle', 'callToActionLabel', 'callToActionUrl', 'audienceMaskUuid', 'recommendations'];
            if ([] !== array_diff(array_keys($arguments), $allowed)) {
                throw new InvalidToolArguments('Unexpected argument.');
            }
            $result = 'create_mailing_draft' === $name
                ? $this->mailingDraftManager->create($arguments)
                : $this->mailingDraftManager->update($this->text($arguments, 'campaignUuid', 36), $this->withoutKey($arguments, 'campaignUuid'));
            $json = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return ['content' => [['type' => 'text', 'text' => $json]], 'isError' => false];
        } catch (InvalidToolArguments $exception) {
            return $this->error($exception->getMessage());
        } catch (JsonException) {
            return $this->error('The mailing draft result was invalid.');
        } catch (Throwable $exception) {
            return $this->error($exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Mailing draft operation failed.');
        }
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function withoutKey(array $arguments, string $key): array
    {
        unset($arguments[$key]);

        return $arguments;
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
            if ([] !== array_diff(array_keys($arguments), ['mailReference', 'body', 'htmlBody'])) {
                throw new InvalidToolArguments('Unexpected argument.');
            }
            $reference = $this->text($arguments, 'mailReference', 1024);
            $body = $this->text($arguments, 'body', 10_000);
            $htmlBody = array_key_exists('htmlBody', $arguments) ? $this->draftBody($arguments, 'htmlBody', 20_000) : null;
            $result = $this->mailDraftWriter->save($reference, $body, $htmlBody);
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
            if ([] !== array_diff(array_keys($arguments), ['mailReference', 'draftMessageId', 'revision', 'body', 'htmlBody'])) {
                throw new InvalidToolArguments('Unexpected argument.');
            }
            $reference = $this->text($arguments, 'mailReference', 1024);
            $draftMessageId = $this->text($arguments, 'draftMessageId', 255);
            $revision = $this->text($arguments, 'revision', 64);
            $body = $this->draftBody($arguments, 'body', 10_000);
            $htmlBody = array_key_exists('htmlBody', $arguments) ? $this->draftBody($arguments, 'htmlBody', 20_000) : null;
            $result = $this->mailDraftEditor->update($reference, $draftMessageId, $revision, $body, $htmlBody);
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
            if ([] !== array_diff(array_keys($arguments), ['recipient', 'subject', 'body', 'htmlBody'])) {
                throw new InvalidToolArguments('Unexpected argument.');
            }
            $recipient = $this->text($arguments, 'recipient', 254);
            if (false === filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidToolArguments('Invalid draft recipient.');
            }
            $subject = $this->text($arguments, 'subject', 255);
            $body = $this->draftBody($arguments, 'body', 10_000);
            $htmlBody = array_key_exists('htmlBody', $arguments) ? $this->draftBody($arguments, 'htmlBody', 20_000) : null;
            $result = $this->standaloneMailDraftWriter->create($recipient, $subject, $body, $htmlBody);
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

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function callStandaloneDraftUpdate(array $arguments): array
    {
        if (!$this->draftComposeEnabled || !$this->draftEditEnabled || null === $this->standaloneMailDraftEditor) {
            return $this->error('Standalone draft editing is disabled.');
        }
        try {
            if ([] !== array_diff(array_keys($arguments), ['draftMessageId', 'revision', 'body', 'htmlBody'])) {
                throw new InvalidToolArguments('Unexpected argument.');
            }
            $draftMessageId = $this->text($arguments, 'draftMessageId', 255);
            $revision = $this->text($arguments, 'revision', 64);
            $body = $this->draftBody($arguments, 'body', 10_000);
            $htmlBody = array_key_exists('htmlBody', $arguments) ? $this->draftBody($arguments, 'htmlBody', 20_000) : null;
            $result = $this->standaloneMailDraftEditor->update($draftMessageId, $revision, $body, $htmlBody);
            $json = json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return ['content' => [['type' => 'text', 'text' => $json]], 'isError' => 'saved' !== ($result['status'] ?? null)];
        } catch (InvalidToolArguments $exception) {
            return $this->error($exception->getMessage());
        } catch (Throwable) {
            return $this->error('Standalone draft update failed.');
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

    /** @param array<string, mixed> $arguments */
    private function optionalDueTime(array $arguments): ?string
    {
        $value = $arguments['dueTime'] ?? null;
        if (null === $value) {
            return null;
        }
        if (!is_string($value) || 1 !== preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $value)) {
            throw new InvalidToolArguments('Invalid indicative time.');
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private function error(string $message): array
    {
        return ['content' => [['type' => 'text', 'text' => $message]], 'isError' => true];
    }
}
