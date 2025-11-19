<?php

namespace Greendot\EshopBundle\Controller\Shop;

use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Attribute\CustomApiEndpoint;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Price;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Repository\Project\ClientRepository;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\ParameterRepository;
use Greendot\EshopBundle\Repository\Project\PaymentTypeRepository;
use Greendot\EshopBundle\Repository\Project\ProductVariantDiscountRepository;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Greendot\EshopBundle\Repository\Project\ProductVariantRepository;
use Greendot\EshopBundle\Repository\Project\PurchaseProductVariantRepository;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Repository\Project\TransportationRepository;
use Greendot\EshopBundle\Service\GoogleAnalytics;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Service\ProductInfoGetter;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class ProductController extends AbstractController
{
    private ProductRepository $productRepository;
    private ProductVariantRepository $productVariantRepository;

    public function __construct(ProductRepository $productRepository, ProductVariantRepository $productVariantRepository)
    {
        $this->productRepository        = $productRepository;
        $this->productVariantRepository = $productVariantRepository;
    }

    #[CustomApiEndpoint]
    #[Route('/api/store-last-viewed-product', name: 'store_last_viewed_product', methods: ['POST'])]
    public function storeLastViewedProduct(Request $request, SessionInterface $session): JsonResponse
    {
        $data        = json_decode($request->getContent(), true);
        $productSlug = $data['productSlug'] ?? null;

        if ($productSlug) {
            $session->set('last_viewed_product', $productSlug);
            return $this->json(['message' => 'Last viewed product stored successfully']);
        }

        return $this->json(['error' => 'Invalid product slug'], 400);
    }

    #[CustomApiEndpoint]
    #[Route('/api/get-last-viewed-product', name: 'get_last_viewed_product', methods: ['GET'])]
    public function getLastViewedProduct(SessionInterface $session): JsonResponse
    {
        $productSlug = $session->get('last_viewed_product');

        if ($productSlug) {
            return $this->json(['productSlug' => $productSlug]);
        }

        return $this->json(['productSlug' => null]);
    }

    #[Route('/{slug}-p', name: 'shop_product', requirements: ['slug' => '[A-Za-z0-9\-]+'], options: ['expose' => true], priority: 20)]
    public function index(Product $product): Response
    {
        $template = 'shop/product/index.html.twig';
        if ($product->getProductViewType()?->getTemplate()){
            $template = $product->getProductViewType()->getTemplate();
        }

        return $this->render($template, [
            'product' => $product,
            'productId' => $product->getId(),
        ]);
    }

    #[Route('/{slug}-p/add', name: 'add_product', priority: 2, requirements: ['slug' => '[A-Za-z0-9\-]+'])]
    public function add(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Product $product,
        Session $session
    ): Response
    {
        if ($session->has('purchase')) {
            $purchase = $session->get('purchase');
        } else {
            $purchase = new Purchase();
        }

        $opv = new PurchaseProductVariant();
        $opv->setProductVariant($product->getProductVariants()->first());

        $purchase->addProductVariant($opv);

        $session->set('purchase', $purchase);

        return $this->redirectToRoute('shop_product', ['slug' => $product->getSlug()]);
    }

    #[CustomApiEndpoint]
    #[Route('/shop/api/cart/add-{variant_id}/amount-{amount}', name: 'add_to_cart', requirements: ['slug' => '[A-Za-z0-9\-]+'], defaults: ['amount' => 1], priority: 2)]
    public function addToCart
    (
        $variant_id,
        $amount,
        RequestStack $requestStack,
        ClientRepository $clientRepository,
        ProductVariantRepository $productVariantRepository,
        PurchaseProductVariantRepository $purchaseProductVariantRepository,
        TransportationRepository $transportationRepository,
        PaymentTypeRepository $paymentTypeRepository,
        ManagePurchase $manageOrder,
        PurchaseRepository $purchaseRepository,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
    ): Response
    {
        $session = $requestStack->getSession();
        if ($session->has('purchase')) {
            $purchase = $purchaseRepository->find($session->get('purchase'));

            $productVariant = $productVariantRepository->find($variant_id);
            $purchaseProductVariant = $purchaseProductVariantRepository->findOneBy(['ProductVariant' => $productVariant, 'purchase' => $purchase]);
            if ($purchaseProductVariant) {
                $purchase->removeProductVariant($purchaseProductVariant);
            }

            if ($amount > 0) {
                $purchase = $manageOrder->addProductVariantToPurchase($purchase, $productVariant, $amount);
            }

            $entityManager->persist($purchase);
            $entityManager->flush();
        } else {
            $productVariant = $productVariantRepository->find($variant_id);

            $purchase = $this->createCart($this->getUser());
            $purchase = $manageOrder->addProductVariantToPurchase($purchase, $productVariant, $amount);

            $entityManager->persist($purchase);
            $entityManager->flush();

            $session->set('purchase', $purchase->getId());
        }

        $response = [
            'amount' => $amount,
            'productVariant' => $productVariant
        ];
        $context = [AbstractNormalizer::GROUPS => ['product_variant:read']];

        return new JsonResponse($serializer->serialize($response, 'json', $context), Response::HTTP_OK, [], true);
    }

    #[CustomApiEndpoint]
    #[Route('/shop/api/cart/add_modal', name: 'cart_add_modal')]
    public function cart_add_modal(RequestStack $requestStack, ProductVariantRepository $productVariantRepository): Response
    {
        $session         = $requestStack->getSession();
        $purchase        = $session->get('purchase');
        $productVariants = [];
        foreach ($purchase->getProductVariants() as $orderProductVariant) {
            $productVariant = $orderProductVariant->getProductVariant()->getId();
            $productVariant = $productVariantRepository->find($productVariant);
            array_push($productVariants, $productVariant);
        }
        return $this->json(
            $this->render('shop/cart/_add_to_cart_modal.html.twig', [
                'productVariants' => $productVariants,
                'order'           => $purchase
            ]),
            headers: ['Content-Type' => 'application/json;charset=UTF-8']
        );
    }

    #[CustomApiEndpoint]
    #[Route('/shop/api/wishlist/add-{variant_id}/amount-{amount}', name: 'add_to_wishlist', requirements: ['slug' => '[A-Za-z0-9\-]+'], defaults: ['amount' => 1], priority: 2)]
    public function addToWishlist
    (
        $variant_id,
        $amount,
        RequestStack $requestStack,
        ProductVariantRepository $productVariantRepository,
        PurchaseProductVariantRepository $purchaseProductVariantRepository,
        ManagePurchase $manageOrder,
        PurchaseRepository $purchaseRepository,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $session = $requestStack->getSession();
        if (!$this->getUser() || !$session->has('wishlist')) {
            // Wishlist should be already initialized if the user is logged in
            return $this->json(['error' => 'Nepodařilo se najít seznam přání'], 400);
        }

        $wishlist = $purchaseRepository->find($session->get('wishlist'));
        $productVariant = $productVariantRepository->find($variant_id);
        $purchaseProductVariant = $purchaseProductVariantRepository->findOneBy(['ProductVariant' => $productVariant, 'purchase' => $wishlist]);
        if ($purchaseProductVariant) {
            $wishlist->removeProductVariant($purchaseProductVariant);
        }

        if ($amount > 0) {
            $wishlist = $manageOrder->addProductVariantToPurchase($wishlist, $productVariant, $amount);
        }

        $entityManager->persist($wishlist);
        $entityManager->flush();

        return new Response();
    }

    #[CustomApiEndpoint]
    #[Route('/shop/api/inquiry/add-{variant_id}/amount-{amount}', name: 'add_to_inquiry', requirements: ['slug' => '[A-Za-z0-9\-]+'], defaults: ['amount' => 1], priority: 2)]
    public function addToInquiry
    (
        $variant_id,
        $amount,
        RequestStack $requestStack,
        ClientRepository $clientRepository,
        ProductVariantRepository $productVariantRepository,
        ManagePurchase $manageOrder,
        GoogleAnalytics $googleAnalytics
    )
    {
        $session = $requestStack->getSession();
        if ($session->has('inquiry')) {
            $purchase = $session->get('inquiry');

            $productVariant = $productVariantRepository->find($variant_id);
            $purchase       = $manageOrder->addProductVariantToPurchase($purchase, $productVariant, $amount);

            $session->set('inquiry', $purchase);
        } else {
            $productVariant = $productVariantRepository->find($variant_id);

            $purchase = new Purchase();
            $purchase->setState('inquiry');
            if ($this->getUser()) {
                $client = $this->getUser();
                $client = $clientRepository->find($client);
                $purchase->setClient($client);
            }
            $purchase = $manageOrder->addProductVariantToPurchase($purchase, $productVariant, $amount);

            $session->set('inquiry', $purchase);
        }

        return $this->json(
            $googleAnalytics->addToCart($productVariant, $amount, 'inquiry'),
            headers: ['Content-Type' => 'application/json;charset=UTF-8']
        );
    }

    #[CustomApiEndpoint]
    #[Route('/shop/api/cart/remove-{variant_id}-{type}', name: 'remove_from_cart', defaults: ['amount' => 0])]
    public function removeFromCart
    (
        $variant_id,
        $type,
        RequestStack $requestStack,
    ): JsonResponse
    {
        $session  = $requestStack->getSession();
        $purchase = $session->get($type);

        foreach ($purchase->getProductVariants() as $orderProductVariant) {
            if ($orderProductVariant->getProductVariant()->getId() == $variant_id) {
                $purchase->removeProductVariant($orderProductVariant);
            }
        }
        $session->set($type, $purchase);
        return $this->json(
            'success',
            headers: ['Content-Type' => 'application/json;charset=UTF-8']
        );
    }

    #[CustomApiEndpoint]
    #[Route('/shop/api/cart/change_amount-{variant_id}-{amount}-{type}', name: 'change_amount_in_cart', requirements: ['slug' => '[A-Za-z0-9\-]+'], priority: 2)]
    public function changeAmountInCart
    (
        $variant_id,
        $amount,
        $type,
        RequestStack $requestStack,
    ): JsonResponse
    {
        $session  = $requestStack->getSession();
        $purchase = $session->get($type);
        if ($purchase) {
            foreach ($purchase->getProductVariants() as $purchaseProductVariant) {
                if ($purchaseProductVariant->getProductVariant()->getId() == $variant_id) {
                    $purchaseProductVariant->setAmount((int)$amount);
                }
            }
        }
        $session->set($type, $purchase);
        return $this->json(
            'success',
            headers: ['Content-Type' => 'application/json;charset=UTF-8']
        );
    }

    #[CustomApiEndpoint]
    #[Route('/shop/api/vue/price_string_for_product/{product}', name: 'api_vue_price_string_for_product')]
    public function getPriceStringForProduct(Product $product, Session $session, CurrencyRepository $deleteThis, ProductInfoGetter $productInfoGetter): JsonResponse
    {
        // TODO REMOVE THIS
        $currency = $deleteThis->find(1);

        //$currency = $session->get('selectedCurrency');

        $finalString = $productInfoGetter->getProductPriceString($product, $currency);

        return $this->json($finalString, headers: ['Content-Type' => 'application/json;chatset=UTF-8']);
    }

    #[CustomApiEndpoint]
    #[Route('/api/parameters/available', name: 'api_parameters_available', methods: ['GET'])]
    public function getAvailableParameters(Request $request, ParameterRepository $parameterRepository): JsonResponse
    {
        $productId = $request->query->get('productId');
        $color     = $request->query->get('color');
        $size      = $request->query->get('size');

        $parameters = $parameterRepository->findAvailableParametersByColorOrSize($productId, $color, $size);

        return $this->json($parameters, 200, [], ['groups' => ['parameter_type:read']]);
    }

    /*
     * TODO přehodit do API Platform - k variantám
     */
    #[CustomApiEndpoint]
    #[Route('/api/product-variant/{id}/prices', name: 'api_get_product_variant_prices', methods: ['GET'])]
    public function getProductVariantPrices(int $id): JsonResponse
    {
        $productVariant = $this->productVariantRepository->find($id);

        if (!$productVariant) {
            return $this->json(['error' => 'Product variant not found'], 404);
        }

        $prices = $productVariant->getPrice()->toArray();

        $currentDate = new \DateTime();

        $validPrices = array_filter($prices, function (Price $price) use ($currentDate) {
            $validFrom = $price->getValidFrom();
            $validUntil = $price->getValidUntil();

            return ($validFrom <= $currentDate) &&
                ($validUntil === null || $validUntil >= $currentDate);
        });

        usort($validPrices, function (Price $a, Price $b) {
            return $a->getMinimalAmount() <=> $b->getMinimalAmount();
        });

        $priceData = array_map(function (Price $price) {
            return [
                'price' => $price->getPrice(),
                'vat' => $price->getVat(),
                'minimalAmount' => $price->getMinimalAmount(),
                'discount' => $price->getDiscount(),
                'isPackage' => $price->isIsPackage(),
                'minPrice' => $price->getMinPrice(),
            ];
        }, $validPrices);

        return $this->json($priceData);
    }

    private function createCart(?Client $client): Purchase
    {
        $cart = new Purchase();
        $cart->setState('draft')
            ->setDateIssue(new \DateTime())
            ->setClient($client);
        return $cart;
    }

}
