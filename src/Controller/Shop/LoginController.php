<?php

namespace Greendot\EshopBundle\Controller\Shop;

use Greendot\EshopBundle\Repository\Project\ClientRepository;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Url\PurchaseUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

class LoginController extends AbstractController
{


    #[Route('/login', name: 'custom_login', priority: 100)]
    public function login(AuthenticationUtils $authenticationUtils, #[CurrentUser] $user = null): Response
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


    //! This is for testing. Is a security problem if uncommented.
    /* 
    #[Route('/api/get_login_link/{id}', name: 'get_login_link')]
    public function getLoginLink(int $id, PurchaseRepository $purchaseRepository, PurchaseUrlGenerator $managePurchase) : Response 
    {
        
    $purchase = $purchaseRepository->find($id);
    $loginLink = $managePurchase->buildOrderDetailUrl($purchase);
    // $client = $purchase->getClient();
    
    // if (!$client) {
        //     return new JsonResponse(['error' => 'Client not found'], Response::HTTP_NOT_FOUND);
        // }
        
        // // Assuming $client is a UserInterface (or similar)
        // $loginLinkDetails = $loginLinkHandler->createLoginLink($client);
        // $orderDetailUrl = 'http://yogashop-24/zakaznik/objednavka/'.$purchase->getId();
        // $loginUrl = $loginLinkDetails->getUrl() . '&redirect=' . urlencode($orderDetailUrl);
        
        return new JsonResponse(['url' => $loginLink]);
    }
    */

    // this reqisters the route. Symfony overrides it so it can be empty
    #[Route('/api/anonymous_login_check', name: 'api_anonymous_login_check')]
    public function anonymousLoginCheck(): void
    { }

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
    public function logout(): RedirectResponse
    {
        return $this->redirectToRoute('web_homepage');
    }
}
