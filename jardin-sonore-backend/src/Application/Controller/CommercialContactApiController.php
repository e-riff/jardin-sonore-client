<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Commercial\CommercialContactRequestInput;
use App\Infrastructure\Commercial\ContactRequestDeliveryStore;
use DomainException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/commercial', name: 'commercial_contact_api_', format: 'json')]
final readonly class CommercialContactApiController
{
    public function __construct(private ContactRequestDeliveryStore $deliveryStore, private ValidatorInterface $validator)
    {
    }

    #[Route('/contact-requests', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $request->toArray();
        foreach (['name', 'emailAddress', 'message', 'submissionKey'] as $field) {
            if (!isset($data[$field]) || !is_string($data[$field])) {
                return new JsonResponse(['error' => 'invalid_contact_data'], 422);
            }
        }
        foreach (['organizationName', 'city', 'phone'] as $field) {
            if (isset($data[$field]) && !is_string($data[$field])) {
                return new JsonResponse(['error' => 'invalid_contact_data'], 422);
            }
        }

        $input = new CommercialContactRequestInput(
            $data['name'],
            $data['emailAddress'],
            $data['message'],
            $data['submissionKey'],
            $data['organizationName'] ?? '',
            $data['city'] ?? '',
            $data['phone'] ?? '',
        );
        if (0 < count($this->validator->validate($input))) {
            return new JsonResponse(['error' => 'invalid_contact_data'], 422);
        }

        try {
            $requestId = $this->deliveryStore->record($input);
        } catch (DomainException) {
            return new JsonResponse(['error' => 'submission_key_conflict'], 409);
        }

        return new JsonResponse(['requestId' => $requestId], 202);
    }
}
