<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Mailing\NewsletterSubscriptionRequestInput;
use App\Infrastructure\Mailing\FreeNewsletterSubscriptionManager;
use App\Infrastructure\Security\PortalRateLimitKeyResolver;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/newsletter', name: 'newsletter_api_', format: 'json')]
final readonly class NewsletterSubscriptionApiController
{
    public function __construct(
        private FreeNewsletterSubscriptionManager $subscriptionManager,
        private PortalRateLimitKeyResolver $rateLimitKeyResolver,
        private ValidatorInterface $validator,
        #[Autowire(service: 'limiter.newsletter_subscription_ip')] private RateLimiterFactoryInterface $ipLimiter,
        #[Autowire(service: 'limiter.newsletter_subscription_address')] private RateLimiterFactoryInterface $addressLimiter,
    ) {
    }

    #[Route('/subscription-requests', name: 'request', methods: ['POST'])]
    public function requestSubscription(Request $request): JsonResponse
    {
        $data = $request->toArray();
        if (!isset($data['emailAddress']) || !is_string($data['emailAddress'])) {
            throw new BadRequestHttpException();
        }
        $input = new NewsletterSubscriptionRequestInput(trim($data['emailAddress']));
        if (0 < count($this->validator->validate($input))) {
            return new JsonResponse(['status' => 'invalid'], 422);
        }
        if (!$this->ipLimiter->create($this->rateLimitKeyResolver->resolve($request))->consume()->isAccepted()) {
            return new JsonResponse(['status' => 'unavailable'], 429);
        }
        $emailAddress = mb_strtolower(trim($input->emailAddress));
        if ($this->addressLimiter->create(hash('sha256', $emailAddress))->consume()->isAccepted()) {
            try {
                $this->subscriptionManager->requestSubscription($emailAddress);
            } catch (TransportExceptionInterface) {
                return new JsonResponse(['status' => 'unavailable'], 503);
            }
        }

        return new JsonResponse(['status' => 'accepted'], 202);
    }

    #[Route('/confirmations/{token}', name: 'state', methods: ['GET'])]
    public function confirmationState(string $token): JsonResponse
    {
        $this->validateTokenFormat($token);

        return new JsonResponse(['state' => $this->subscriptionManager->confirmationState($token)->value], headers: ['Cache-Control' => 'no-store']);
    }

    #[Route('/confirmations', name: 'confirm', methods: ['POST'])]
    public function confirm(Request $request): JsonResponse
    {
        $data = $request->toArray();
        $token = $data['token'] ?? null;
        if (!is_string($token)) {
            throw new BadRequestHttpException();
        }
        $this->validateTokenFormat($token);

        return new JsonResponse(['state' => $this->subscriptionManager->confirm($token)->value], headers: ['Cache-Control' => 'no-store']);
    }

    private function validateTokenFormat(string $token): void
    {
        if (1 !== preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new BadRequestHttpException();
        }
    }
}
