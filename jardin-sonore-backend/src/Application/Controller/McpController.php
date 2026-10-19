<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Mcp\McpProtocol;
use JsonException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/mcp', name: 'mcp_', methods: ['POST', 'GET'])]
final readonly class McpController
{
    public function __construct(
        private McpProtocol $protocol,
        #[Autowire('%app.mcp.bearer_token%')]
        private string $bearerToken,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if ('' === $this->bearerToken) {
            return new JsonResponse(['error' => 'mcp_not_configured'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $authorization = $request->headers->get('Authorization', '');
        if (1 !== preg_match('/^Bearer\s+(\S+)$/iD', $authorization, $matches) || !hash_equals($this->bearerToken, $matches[1])) {
            return new JsonResponse(['error' => 'authentication_required'], Response::HTTP_UNAUTHORIZED);
        }

        $origin = $request->headers->get('Origin');
        if (null !== $origin && $origin !== $request->getSchemeAndHttpHost()) {
            return new JsonResponse(['error' => 'invalid_origin'], Response::HTTP_FORBIDDEN);
        }

        if ($request->isMethod('GET')) {
            return new Response('', Response::HTTP_METHOD_NOT_ALLOWED, ['Allow' => 'POST']);
        }

        $acceptedTypes = array_map(static fn (string $type): string => trim(explode(';', $type, 2)[0]), explode(',', strtolower($request->headers->get('Accept', ''))));
        if (!in_array('application/json', $acceptedTypes, true) || !in_array('text/event-stream', $acceptedTypes, true)) {
            return new JsonResponse(['error' => 'unsupported_accept_header'], Response::HTTP_NOT_ACCEPTABLE);
        }

        $contentType = strtolower(trim(explode(';', $request->headers->get('Content-Type', ''), 2)[0]));
        if ('application/json' !== $contentType) {
            return new JsonResponse(['error' => 'unsupported_content_type'], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        try {
            $message = json_decode($request->getContent(), true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new JsonResponse(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'Parse error']], Response::HTTP_BAD_REQUEST);
        }

        if (!is_array($message)) {
            return new JsonResponse(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32600, 'message' => 'Invalid Request']], Response::HTTP_BAD_REQUEST);
        }

        $response = $this->protocol->handle($message);
        if (null === $response) {
            return new Response('', Response::HTTP_ACCEPTED);
        }

        return new JsonResponse($response);
    }
}
