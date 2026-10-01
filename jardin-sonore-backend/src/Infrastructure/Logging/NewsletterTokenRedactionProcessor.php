<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Throwable;

#[AsMonologProcessor]
final class NewsletterTokenRedactionProcessor implements ProcessorInterface
{
    private const string TOKEN_PATH_PATTERN = '~(/(?:api/newsletter/confirmations|newsletter/confirmer)/)[a-f0-9]{64}~';

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = [];
        foreach ($record->context as $key => $value) {
            $context[$key] = $this->redact($value, $key);
        }

        return $record->with(message: $this->redactString($record->message), context: $context);
    }

    private function redact(mixed $value, int|string $key): mixed
    {
        if (is_array($value)) {
            foreach ($value as $childKey => $childValue) {
                $value[$childKey] = $this->redact($childValue, $childKey);
            }
        } elseif (is_string($value)) {
            return 'token' === $key && 1 === preg_match('/^[a-f0-9]{64}$/D', $value) ? '[redacted]' : $this->redactString($value);
        } elseif ($value instanceof Throwable && 1 === preg_match(self::TOKEN_PATH_PATTERN, $value->getMessage())) {
            return ['class' => $value::class, 'message' => $this->redactString($value->getMessage()), 'code' => $value->getCode(), 'trace' => $this->redactString($value->getTraceAsString())];
        }

        return $value;
    }

    private function redactString(string $value): string
    {
        return preg_replace(self::TOKEN_PATH_PATTERN, '$1[redacted]', $value) ?? $value;
    }
}
