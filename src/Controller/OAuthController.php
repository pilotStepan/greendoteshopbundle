<?php

namespace Greendot\EshopBundle\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OAuthController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google')]
    public function connectGoogle(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry
            ->getClient('google')
            ->redirect([
                'email', 'profile'
            ]);
    }

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
                'email', 'public_profile'
            ]);
    }

    #[Route('/connect/facebook/check', name: 'connect_facebook_check')]
    public function connectFacebookCheck(): Response
    {
        return new Response('Facebook check');
    }

    #[Route('/auth/callback', name: 'auth_callback')]
    public function authCallback(Request $request): Response
    {
        $token = $request->query->get('token');
        $origin = $request->getSchemeAndHttpHost();

        $content = <<<HTML
            <!DOCTYPE html>
            <html>
            <body>
                <script>
                    window.opener.postMessage({ token: "$token" }, "$origin");
                    window.close();
                </script>
            </body>
            </html>
        HTML;

        return new Response($content);
    }
}