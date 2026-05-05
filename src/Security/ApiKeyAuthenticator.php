<?php

namespace App\Security;

use App\Repository\ApiKeyRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Authenticates requests via the X-API-KEY header.
 * The raw key is SHA-256 hashed and looked up in the api_key table.
 * On success, grants ROLE_API without requiring a registered user or JWT token.
 */
class ApiKeyAuthenticator extends AbstractAuthenticator
{
    public function __construct(private readonly ApiKeyRepository $apiKeyRepository) {}

    // Only triggers when the request carries the X-API-KEY header.
    public function supports(Request $request): ?bool
    {
        return $request->headers->has('X-API-KEY');
    }

    public function authenticate(Request $request): Passport
    {
        $rawKey = $request->headers->get('X-API-KEY');
        // Keys are never stored in plain text; always compare against the SHA-256 hash.
        $hash   = hash('sha256', $rawKey);

        $apiKey = $this->apiKeyRepository->findOneBy(['keyHash' => $hash, 'isActive' => true]);

        if (!$apiKey) {
            throw new CustomUserMessageAuthenticationException('Invalid or inactive API key.');
        }

        // SelfValidatingPassport skips credential checking — validation already done above.
        // InMemoryUser represents the API consumer; it is not a persisted User entity.
        return new SelfValidatingPassport(
            new UserBadge('api_key_' . $apiKey->getId(), fn() => new InMemoryUser('api_consumer', null, ['ROLE_API']))
        );
    }

    // Return null to let the request continue to the controller.
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'status'  => 'error',
            'message' => $exception->getMessageKey(),
        ], Response::HTTP_UNAUTHORIZED);
    }
}
