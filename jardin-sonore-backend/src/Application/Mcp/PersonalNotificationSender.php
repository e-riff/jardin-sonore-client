<?php

declare(strict_types=1);

namespace App\Application\Mcp;

use App\Infrastructure\Mail\DraftHtmlBody;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/** @see PersonalNotificationStore */
final readonly class PersonalNotificationSender
{
    public function __construct(
        private PersonalNotificationStore $notificationStore,
        private TransportInterface $transport,
        private LoggerInterface $logger,
        #[Autowire('%env(DEFAULT_CONTACT)%')] private string $senderAddress,
        #[Autowire('%app.mcp.personal_notification_recipient%')] private string $recipientAddress,
    ) {
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function send(array $arguments): array
    {
        if ([] !== array_diff(array_keys($arguments), ['requestId', 'subject', 'body', 'htmlBody'])) {
            throw new InvalidArgumentException('Unexpected notification argument.');
        }
        $requestId = $arguments['requestId'] ?? null;
        if (!is_string($requestId) || 1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/D', $requestId)) {
            throw new InvalidArgumentException('Invalid notification requestId.');
        }
        $subject = $this->text($arguments['subject'] ?? null, 255, true);
        $body = $this->text($arguments['body'] ?? null, 10_000);
        $html = array_key_exists('htmlBody', $arguments) ? DraftHtmlBody::sanitize($this->text($arguments['htmlBody'], 20_000)) : null;
        foreach ([$this->senderAddress, $this->recipientAddress] as $address) {
            if (false === filter_var($address, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Personal notification mail configuration is unavailable.');
            }
        }
        $fingerprint = hash('sha256', json_encode([$this->senderAddress, $this->recipientAddress, $subject, $body, $html], JSON_THROW_ON_ERROR));
        $reservation = $this->notificationStore->reserve($requestId, $fingerprint);
        if (!$reservation['isNew']) {
            return ['status' => $reservation['status'], 'requestId' => $requestId, 'attempted' => false];
        }
        $status = 'uncertain';
        try {
            $email = (new Email())->from($this->senderAddress)->to($this->recipientAddress)->subject($subject)->text($body, 'utf-8');
            if (null !== $html) {
                $email->html($html, 'utf-8');
            }
            // Synchronous transport: queue acceptance must never be mistaken for SMTP acceptance.
            $sentMessage = $this->transport->send($email, new Envelope(new Address($this->senderAddress), [new Address($this->recipientAddress)]));
            if (null !== $sentMessage) {
                $status = 'accepted';
            }
        } catch (Throwable $exception) {
            $this->logger->warning('MCP personal notification SMTP outcome uncertain.', ['requestId' => $requestId, 'exceptionClass' => $exception::class]);
        }
        try {
            $this->notificationStore->finish($requestId, $status);
        } catch (Throwable $exception) {
            // Keep the reservation in place: repeating the request must not send again.
            $this->logger->error('MCP personal notification receipt could not be completed.', ['requestId' => $requestId, 'exceptionClass' => $exception::class]);

            return ['status' => 'uncertain', 'requestId' => $requestId, 'attempted' => true];
        }

        return ['status' => $status, 'requestId' => $requestId, 'attempted' => true];
    }

    private function text(mixed $value, int $maxLength, bool $header = false): string
    {
        if (!is_string($value) || '' === trim($value) || $maxLength < mb_strlen($value)
            || 1 === preg_match($header ? '/[\x00-\x1F\x7F]/' : '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            throw new InvalidArgumentException('Invalid notification text or subject.');
        }

        return $value;
    }
}
