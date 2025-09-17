<?php

namespace Greendot\EshopBundle\Security\OAuthAuthenticator;

use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Greendot\EshopBundle\Entity\Project\Client;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class GoogleAuthenticator extends OAuth2Authenticator
{
    use TargetPathTrait;

    public function __construct(
        private readonly ClientRegistry           $clientRegistry,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly EntityManagerInterface   $entityManager,
        private readonly RouterInterface          $router,
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(
            new UserBadge($accessToken->getToken(), function () use ($accessToken, $client) {
                /** @var GoogleUser $googleUser */
                $googleUser = $client->fetchUserFromToken($accessToken);
                $email = $googleUser->getEmail();

                $existingUser = $this->entityManager->getRepository(Client::class)->findNonAnonymousByEmail($email);
                if ($existingUser) return $existingUser;

                $user = new Client();
                $user->setMail($email);
                $user->setName($googleUser->getFirstName());
                $user->setSurname($googleUser->getLastName());

                $this->entityManager->persist($user);
                $this->entityManager->flush();

                return $user;
            }),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $target = $this->getTargetPath($request->getSession(), $firewallName)
            ?? $request->query->get('target')
            ?? $this->router->generate('web_homepage');

        $jwt = $this->jwtManager->create($token->getUser());

        return new RedirectResponse(
            $this->router->generate('auth_callback', [
                'token' => $jwt,
                'redirect' => $target,
            ]),
        );
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        return new Response($message, Response::HTTP_FORBIDDEN);
    }
}