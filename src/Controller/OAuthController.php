<?php

namespace Greendot\EshopBundle\Controller;

use Greendot\EshopBundle\Attribute\CustomApiEndpoint;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class OAuthController extends AbstractController
{
 
    #[Route('/connect/google', name: 'connect_google')]
    public function connectGoogle(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry
            ->getClient('google')
            ->redirect([
                'email', 'profile',
            ])
        ;
    }

    #[CustomApiEndpoint]
    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectGoogleCheck(): Response
    {
        return new Response('Google check');
    }

    #[Route('/connect/facebook', name: 'connect_facebook')]
    public function connectFacebook(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry
            ->getClient('facebook')
            ->redirect([
                'email', 'public_profile',
            ])
        ;
    }

    #[CustomApiEndpoint]
    #[Route('/connect/facebook/check', name: 'connect_facebook_check')]
    public function connectFacebookCheck(): Response
    {
        return new Response('Facebook check');
    }

    #[CustomApiEndpoint]
    #[Route('/auth/callback', name: 'auth_callback')]
    public function authCallback(Request $request): Response
    {
        $token = (string)$request->query->get('token');
        $redirect = (string)($request->query->get('redirect') ?? '/');
        $origin = $request->getSchemeAndHttpHost();

        $tokenJs = json_encode($token, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $redirectJs = json_encode($redirect, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $originJs = json_encode($origin, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

        $content = <<<HTML
            <!DOCTYPE html>
            <html lang="">
            <body>
            <script>
                (function () {
                    try {
                        if (window.opener) {
                            window.opener.postMessage({ token: $tokenJs, redirect: $redirectJs }, $originJs);
                            window.close();
                            return;
                        }
                    } catch (e) {}
                    window.location.href = $redirectJs;
                })();
            </script>
            </body>
            </html>
        HTML;

        return new Response($content);
    }
}