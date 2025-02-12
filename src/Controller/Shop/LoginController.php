<?php

namespace Greendot\EshopBundle\Controller\Shop;

use Greendot\EshopBundle\Repository\Project\ClientRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class LoginController extends AbstractController
{


    #[Route('/login', name: 'custom_login', priority: 100)]
    public function login(#[CurrentUser] $user = null, AuthenticationUtils $authenticationUtils): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('login/index.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
        ]);
    }

    #[Route('/api_login', name: 'custom_api_login', methods: ['POST'], priority: 100)]
    public function api_login(#[CurrentUser] $user = null): Response
    {


            return $this->json([
                'user' => $user ? $user->getFullname() : null,
            ]);

    }
    /*
    #[Route('/custom_login', name: 'shop_custom_login', priority: 100)]
    public function index(
        Request                     $request,
        ClientRepository            $clientRepository,
        UserPasswordHasherInterface $hasher,
        Security                    $security
    ): JsonResponse|Response
    {
        $username = $request->get("_username");
        $password = $request->get("_password");

        if (!$username || !$password) {
            return $this->json([
                'success' => false,
                'message' => 'Vyskytla se chyba, zkuste to prosím znovu'
            ], 400);
        }

        $user = $clientRepository->findOneBy(['mail' => $username, 'isAnonymous' => false]);

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Nesprávný login nebo heslo'
            ], 401);
        }

        if (!$user->isVerified()) {
            return $this->json([
                'success' => false,
                'message' => 'Účet není ověřen'
            ], 403);
        }

        $login = $hasher->isPasswordValid($user, $password);

        if (!$login) {
            return $this->json([
                'success' => false,
                'message' => 'Nesprávný login nebo heslo'
            ], 401);
        }

        $security->login($user);

        $currentRoute = $request->attributes->get('_route');
        if ($currentRoute === 'shop_register_thank_you') {
            return $this->redirectToRoute('web_homepage');
        }

        return $this->redirect($request->headers->get('referer'));
    }
    */

    #[Route('/logout', name: 'shop_logout', priority: 100)]
    public function logout(): void
    {
    }
}
