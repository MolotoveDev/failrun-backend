<?php
namespace App\Security;

use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class JwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly JWTEncoderInterface $jwtEncoder,
        private readonly UserRepository $userRepository
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization') 
            && str_starts_with($request->headers->get('Authorization'), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $token = substr($request->headers->get('Authorization'), 7);
        
        try {
            $payload = $this->jwtEncoder->decode($token);
        } catch (\Exception $e) {
            throw new CustomUserMessageAuthenticationException('Invalid token: ' . $e->getMessage());
        }

        $username = $payload['username'] ?? null;
        if (!$username) {
            throw new CustomUserMessageAuthenticationException('Token missing username');
        }

        return new SelfValidatingPassport(
            new UserBadge($username, function($username) {
                return $this->userRepository->findOneBy(['username' => $username]);
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'status' => 'error',
            'message' => $exception->getMessage(),
        ], Response::HTTP_UNAUTHORIZED);
    }
}
