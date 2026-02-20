<?php

namespace Greendot\EshopBundle\Controller\Shop;

use Throwable;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\Registry;
use Greendot\EshopBundle\Form\ClientFormType;
use Symfony\Component\HttpFoundation\Request;
use Greendot\EshopBundle\Service\ManageMails;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\WishlistService;
use Greendot\EshopBundle\Service\PurchaseApiModel;
use Greendot\EshopBundle\Url\PurchaseUrlGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Greendot\EshopBundle\Attribute\CustomApiEndpoint;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Greendot\EshopBundle\Service\PaymentGateway\GPWebpay;
use Greendot\EshopBundle\Service\Parcel\ParcelServiceProvider;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PurchaseController extends AbstractController
{

    #[Route('/order/verify', name: 'shop_order_verify', methods: ['GET'])]
    public function verifyOrder(
        Request                $request,
        GPWebpay               $gpWebpay,
        EntityManagerInterface $entityManager,
        Registry               $workflowRegistry,
        PurchaseUrlGenerator   $urlGenerator,
        LoggerInterface        $logger,
    ): Response
    {
        $purchase = null;
        $paymentId = (string)$request->query->get('ORDERNUMBER', '');

        try {
            if ($paymentId === '') {
                throw $this->createNotFoundException('ORDERNUMBER not found');
            }

            $purchase = $entityManager->getRepository(Purchase::class)->findByPaymentId($paymentId);

            // Verify via gateway
            $gatewayResponse = $gpWebpay->verifyLink();
            $isOk = ($gatewayResponse->getPRCODE() === 0) && ($gatewayResponse->getSRCODE() === 0);

            // Update workflow state
            $workflow = $workflowRegistry->get($purchase);
            $transition = $isOk ? 'payment' : 'payment_issue';
            $workflow->apply($purchase, $transition);
            $entityManager->flush();

            return $this->redirectToRoute($urlGenerator->buildOrderEndscreenUrl($purchase));
        } catch (Throwable $e) {
//            dd($e);

            $logger->error('Error during order verification', [
                'purchaseId' => $purchase?->getId(),
                'paymentId' => $paymentId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            if ($purchase) {
                try {
                    $workflowRegistry->get($purchase)->apply($purchase, 'payment_issue');
                    $entityManager->flush();
                } catch (Throwable $inner) {
                    $logger->error('Failed to apply payment_issue transition after verification error', [
                        'purchaseId' => $purchase->getId(),
                        'paymentId' => $paymentId,
                        'exception' => $inner::class,
                        'message' => $inner->getMessage(),
                    ]);
                }
                // TODO?: Create endscreen for failed payment
                return $this->redirectToRoute($urlGenerator->buildOrderEndscreenUrl($purchase));
            } else {
                // TODO?: Create endscreen for failed payment
                return $this->redirectToRoute('web_homepage');
            }
        }
    }


    #[Route('/order/cancel', name: 'order_cancel', methods: ['GET'])]
    public function cancelOrder(
        Request                $request,
        RequestStack           $requestStack,
        PurchaseRepository     $purchaseRepository,
        Registry               $workflow,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $id = $request->query->get('id');
        $redirectRoute = $request->query->get('redirect', 'web_homepage');
        $purchase = $purchaseRepository->find($id);

        $this->denyAccessUnlessGranted('view', $purchase);

        $flow = $workflow->get($purchase);
        if ($purchase->getState() == 'draft') {
            $flow->apply($purchase, 'create');
        }
        if ($flow->can($purchase, 'cancellation')) {
            $flow->apply($purchase, 'cancellation');
            $entityManager->flush();

        }

        $session = $requestStack->getSession();
        if ($session->has('purchase')) {
            $cart = $purchaseRepository->find($session->get('purchase'));
            if ($cart->getId() === $purchase->getId()) {
                $session->remove('purchase');
            }
        }

        return $this->redirectToRoute($redirectRoute);
    }


    #[CustomApiEndpoint]
    #[Route('/api/remove-variant/{productVariantId}', name: 'api_remove_order_item', methods: ['DELETE'], options: ['expose' => true])]
    public function removeOrderItem(int $productVariantId, SessionInterface $session): JsonResponse
    {
        $purchase = $session->get('purchase');

        if ($purchase) {
            $productVariants = $purchase->getProductVariants();

            foreach ($productVariants as $productVariant) {
                if ($productVariant->getProductVariant()->getId() == $productVariantId) {
                    $purchase->removeProductVariant($productVariant);
                    break;
                }
            }

            $session->set('purchase', $purchase);

            $purchaseApiModel = new PurchaseApiModel();
            $purchaseApiModel->parseEntity($purchase);

            return new JsonResponse($purchaseApiModel, 200);
        }

        return new JsonResponse(['error' => 'Order not found'], 404);
    }


    #[CustomApiEndpoint]
    #[Route('/api/purchase/{id}/create-parcel', name: 'api_purchase_create_parcel', methods: ['POST'])]
    public function createParcel(
        Purchase               $purchase,
        ParcelServiceProvider  $provider,
        EntityManagerInterface $entityManager,
    ): JsonResponse
    {
        $parcelService = $provider->getByPurchase($purchase);
        $parcelId = $parcelService?->createParcel($purchase);

        if (!$parcelId) {
            return new JsonResponse(['message' => 'Failed to create parcel'], 500);
        }

        $purchase->setTransportNumber($parcelId);
        $entityManager->flush();

        return new JsonResponse([
            'message' => 'Parcel created successfully',
            'parcelId' => $parcelId,
        ]);
    }

    #[CustomApiEndpoint]
    #[Route('/api/purchase/{id}/parcel-status', name: 'api_purchase_parcel_status', methods: ['GET'])]
    public function getParcelStatus(
        Purchase              $purchase,
        ParcelServiceProvider $provider,
    ): JsonResponse
    {
        $parcelService = $provider->getByPurchase($purchase);
        $status = $parcelService->getParcelStatus($purchase);

        if (!$status) {
            return new JsonResponse(['message' => 'Failed to get parcel status'], 500);
        }

        return new JsonResponse($status);
    }

    #[Route('/objednavka/{path<.*>?}', name: 'shop_order_steps', options: ['expose' => true], priority: 100)]
    public function orderSteps(): Response
    {
        return $this->render('shop/cart/steps.html.twig');
    }

    #[CustomApiEndpoint]
    #[Route('/api/client/form', name: 'api_client_form', methods: ['GET'])]
    public function getClientForm(SerializerInterface $serializer): Response|JsonResponse
    {
        $form = $this->createForm(ClientFormType::class, new Client());
        $formView = $form->createView();

        $formData = $serializer->serialize($formView, 'json');

        return new Response($formData, 200, ['Content-Type' => 'application/json']);
    }

    #[CustomApiEndpoint]
    #[Route('/shop/api/session/currency-{currency}', name: "shop_api_session_currency")]
    public function shopApiSessionCurrency(Currency $currency)
    {
        return new JsonResponse([
            'symbol' => $currency->getSymbol(),
            'name' => $currency->getName(),
            'rounding' => $currency->getRounding(),
            'conversion_rate' => $currency->getConversionRate(),
        ], 200);
    }

    #[Route('/api/purchase/{id}/continue', name: 'purchase_continue')]
    public function setDraftAsCart(
        Purchase         $purchase,
        SessionInterface $session,
        Request          $request,
        ManagePurchase   $managePurchase,
    ): RedirectResponse
    {
        // Check user login and ownership
        $user = $this->getUser();
        $returnTo = $request->query->get('returnTo', '/');

        if (!$user || $user !== $purchase->getClient()) {
            throw new AccessDeniedHttpException("This purchase does not belong to the current user.");
        }

        // Check if state is 'draft'
        if ($purchase->getState() !== 'draft') {
            throw new BadRequestHttpException("Purchase ID {$purchase->getId()} is not in draft state.");
        }


        // Check if purchase is valid
        if (!$managePurchase->isPurchaseValid($purchase)) {
            $this->addFlash('error', 'Produkty v objednávce jsou již nedostupné.');
            return $this->redirect($returnTo);
        }

        // Set session variable
        $session->set('purchase', $purchase->getId());

        // Redirect to page
        return $this->redirect('/objednavka/obsah');
    }

    #[Route('/api/purchase/{id}/repeat', name: 'purchase_repeat')]
    public function putPurchaseProductsToCart(
        Purchase               $purchase,
        SessionInterface       $session,
        Request                $request,
        ManagePurchase         $managePurchase,
        EntityManagerInterface $entityManager,
    ): RedirectResponse
    {
        // Check user login and ownership
        $user = $this->getUser();
        $returnTo = $request->query->get('returnTo', '/');

        if (!$user || $user !== $purchase->getClient()) {
            throw new AccessDeniedHttpException("This purchase does not belong to the current user.");
        }

        // Check if purchase is valid
        if (!$managePurchase->isPurchaseValid($purchase)) {
            $this->addFlash('error', 'Produkty v objednávce jsou již nedostupné.');
            return $this->redirect($returnTo);
        }
        // set cart purchase
        $cartPurchase = new Purchase();
        $cartPurchase->setDateIssue(new \DateTime());
        $cartPurchase->setState('draft');
        $cartPurchase->setClient($user);

        // Put product variants to cart
        $purchaseProductVariants = $purchase->getProductVariants();
        foreach ($purchaseProductVariants as $purchaseProductVariant) {
            $productVariant = $purchaseProductVariant->getProductVariant();
            $amount = $purchaseProductVariant->getAmount();
            $managePurchase->addProductVariantToPurchase($cartPurchase, $productVariant, $amount);
        }

        // persist
        $entityManager->persist($cartPurchase);
        $entityManager->flush();

        // set session purchase
        $session->set('purchase', $cartPurchase->getId());

        // Redirect to page
        return $this->redirect('/objednavka/obsah');
    }

    #[Route('/seznam-prani', name: 'shop_wishlist', options: ['expose' => true], priority: 2)]
    public function wishlist(RequestStack $requestStack, WishlistService $wishlistService, PurchaseRepository $repo): Response
    {
        if (!$this->getUser()) return $this->redirect('/');

        $wishlistId = $requestStack->getSession()->get('wishlist');
        $wishlist = $repo->find($wishlistId);
        $urlToken = $wishlistService->generateUrlToken($wishlist);

        return $this->render('wishlist/index.html.twig', ['props' => [
            'urlToken' => $urlToken,
        ]]);
    }

    #[Route('/seznam-prani/{token}', name: 'shop_wishlist_shared', priority: 2)]
    public function wishlistShared(string $token, WishlistService $wishlistService, SerializerInterface $serializer): Response
    {
        try {
            $wishlist = $wishlistService->getFromUrlToken($token);
        } catch (\Throwable $e) {
            throw $this->createNotFoundException('Seznam přání nebyl nalezen');
        }
        $wishlistService->preparePrices($wishlist);
        $wishlistDto = $serializer->normalize($wishlist, null, ['groups' => ['purchase:wishlist']]);

        return $this->render('wishlist/shared.html.twig', ['props' => [
            'wishlist' => $wishlistDto,
        ]]);
    }

    #[CustomApiEndpoint]
    #[Route('/api/wishlist/send-email', name: 'api_wishlist_send_email', methods: ['POST'])]
    public function sendWishlistEmail(Request $request, WishlistService $wishlistService, ManageMails $manageMails, LoggerInterface $logger): Response
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $token = $data['token'] ?? null;

        if (!$email || !$token) {
            return new JsonResponse(['error' => 'E-mail a token jsou povinné'], 400);
        }

        try {
            $wishlist = $wishlistService->getFromUrlToken($token);
            $manageMails->sendWishlistEmail($email, $wishlist);
            return new JsonResponse(['message' => 'Seznam přání byl úspěšně odeslán'], 200);
        } catch (\UnexpectedValueException|\OutOfBoundsException $e) {
            return new JsonResponse(['error' => 'Seznam přání nebyl nalezen'], 400);
        } catch (\Throwable $e) {
            $logger->error('Error sending wishlist email', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return new JsonResponse(['error' => 'E-mail se nepodařilo odeslat. Prosím zkuste to znovu.'], 500);
        }
    }
}
