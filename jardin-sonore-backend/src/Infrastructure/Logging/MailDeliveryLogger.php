<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mailer\Event\FailedMessageEvent;
use Symfony\Component\Mailer\Event\SentMessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[WithMonologChannel('mail')]
final readonly class MailDeliveryLogger
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    #[AsEventListener]
    public function onSent(SentMessageEvent $event): void
    {
        $sentMessage = $event->getMessage();
        $message = $sentMessage->getOriginalMessage();
        $this->logger->info('Mail transport accepted.', [
            'message_id' => $sentMessage->getMessageId(),
            'recipients' => array_map(static fn (Address $address): string => $address->getAddress(), $sentMessage->getEnvelope()->getRecipients()),
            'subject' => $message instanceof Email ? $message->getSubject() : null,
        ]);
    }

    #[AsEventListener]
    public function onFailed(FailedMessageEvent $event): void
    {
        $message = $event->getMessage();
        $this->logger->error('Mail transport failed.', [
            'recipients' => $message instanceof Email ? array_map(static fn (Address $address): string => $address->getAddress(), array_merge($message->getTo(), $message->getCc(), $message->getBcc())) : [],
            'subject' => $message instanceof Email ? $message->getSubject() : null,
            'error_class' => $event->getError()::class,
        ]);
    }
}
