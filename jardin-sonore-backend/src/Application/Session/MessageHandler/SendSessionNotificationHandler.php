<?php

declare(strict_types=1);

namespace App\Application\Session\MessageHandler;

use App\Application\Session\Message\SendSessionNotificationMessage;
use App\Application\Session\SessionNotificationMailSenderInterface;
use App\Infrastructure\Doctrine\Entity\SessionNotificationDeliveryEntity;
use App\Infrastructure\Session\SessionNotificationDeliveryStore;
use App\Infrastructure\Session\SessionNotificationRecipientReader;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/** @see SendSessionNotificationMessage */
#[AsMessageHandler]
final readonly class SendSessionNotificationHandler
{
    public function __construct(
        private SessionNotificationDeliveryStore $sessionNotificationDeliveryStore,
        private SessionNotificationRecipientReader $sessionNotificationRecipientReader,
        private LoggerInterface $logger,
        private ?SessionNotificationMailSenderInterface $sessionNotificationMailSender = null,
    ) {
    }

    public function __invoke(SendSessionNotificationMessage $message): void
    {
        if (null === $this->sessionNotificationMailSender) {
            throw new LogicException('Session notification mail sender is not configured yet.');
        }

        try {
            $this->sessionNotificationDeliveryStore->deliver($message->deliveryId, function (SessionNotificationDeliveryEntity $sessionNotificationDeliveryEntity): bool {
                $sessionNotificationMailView = $this->sessionNotificationRecipientReader->mailViewFor($sessionNotificationDeliveryEntity);
                if (null === $sessionNotificationMailView) {
                    return false;
                }

                $this->sessionNotificationMailSender->send($sessionNotificationMailView);

                return true;
            });
        } catch (Throwable $throwable) {
            $this->logger->error('Session notification delivery failed.', ['delivery_id' => $message->deliveryId, 'exception' => $throwable]);

            throw $throwable;
        }
    }
}
