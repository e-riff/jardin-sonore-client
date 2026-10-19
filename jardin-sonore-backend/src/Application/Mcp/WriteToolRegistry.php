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
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function definitions(): array
    {
        return [
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
