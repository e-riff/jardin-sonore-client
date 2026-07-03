<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin\Formatter;

final class ContactDisplayFormatter
{
    private const string INACTIVE_PREFIX = '__inactive__:';

    public static function emailLink(mixed $value): string
    {
        [$emailAddress, $inactive] = self::extractInactiveState($value);

        if ('' === $emailAddress || '—' === $emailAddress) {
            return self::escape($emailAddress);
        }

        $escapedEmailAddress = self::escape($emailAddress);

        if ($inactive) {
            return sprintf('<span style="color:#7a746f; font-style:italic;">%s</span>', $escapedEmailAddress);
        }

        $mailTo = self::escape("mailto:{$emailAddress}");

        return sprintf('<a href="%s">%s</a>', $mailTo, $escapedEmailAddress);
    }

    public static function phoneLink(mixed $value): string
    {
        [$phoneNumber, $inactive] = self::extractInactiveState($value);

        if ('' === $phoneNumber || '—' === $phoneNumber) {
            return self::escape($phoneNumber);
        }

        $callablePhoneNumber = self::callablePhoneNumber($phoneNumber);
        $displayPhoneNumber = self::displayPhoneNumber($callablePhoneNumber);

        if ($inactive) {
            return sprintf('<span style="color:#7a746f; font-style:italic;">%s</span>', self::escape($displayPhoneNumber));
        }

        return sprintf('<a href="tel:%s">%s</a>', self::escape($callablePhoneNumber), self::escape($displayPhoneNumber));
    }

    public static function emailSummary(mixed $value): string
    {
        return self::summary($value, self::emailLink(...));
    }

    public static function phoneSummary(mixed $value): string
    {
        return self::summary($value, self::phoneLink(...));
    }

    public static function textSummary(mixed $value): string
    {
        return nl2br(self::escape((string) $value));
    }

    private static function summary(mixed $value, callable $lineFormatter): string
    {
        $lines = preg_split('/\R/', (string) $value) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => '' !== $line));

        if ([] === $lines) {
            return '';
        }

        return implode('<br>', array_map($lineFormatter, $lines));
    }

    public static function inactiveValue(string $value): string
    {
        return self::INACTIVE_PREFIX . $value;
    }

    private static function callablePhoneNumber(string $phoneNumber): string
    {
        $phoneNumber = preg_replace('/[^\d+]/', '', $phoneNumber) ?? '';

        if (str_starts_with($phoneNumber, '00')) {
            $phoneNumber = '+' . substr($phoneNumber, 2);
        }

        if (1 === preg_match('/^0\d{9}$/', $phoneNumber)) {
            return '+33' . substr($phoneNumber, 1);
        }

        return $phoneNumber;
    }

    private static function displayPhoneNumber(string $phoneNumber): string
    {
        if (1 === preg_match('/^\+33\d{9}$/', $phoneNumber)) {
            return '+33 ' . substr($phoneNumber, 3, 1) . ' ' . implode(' ', str_split(substr($phoneNumber, 4), 2));
        }

        if (str_starts_with($phoneNumber, '+')) {
            return '+' . implode(' ', str_split(substr($phoneNumber, 1), 2));
        }

        if (1 === preg_match('/^0\d{9}$/', $phoneNumber)) {
            return implode(' ', str_split($phoneNumber, 2));
        }

        return $phoneNumber;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * @return array{string, bool}
     */
    private static function extractInactiveState(mixed $value): array
    {
        $stringValue = trim((string) $value);

        if (!str_starts_with($stringValue, self::INACTIVE_PREFIX)) {
            return [$stringValue, false];
        }

        return [substr($stringValue, strlen(self::INACTIVE_PREFIX)), true];
    }
}
