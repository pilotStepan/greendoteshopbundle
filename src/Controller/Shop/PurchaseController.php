<?php

namespace Greendot\EshopBundle\Controller\Shop;

use Greendot\EshopBundle\Entity\Project\ClientAddress;
use Greendot\EshopBundle\Entity\Project\Note;
use Greendot\EshopBundle\Entity\Project\Payment;
use Greendot\EshopBundle\Entity\Project\PaymentAction;
use Greendot\EshopBundle\Enum\VoucherCalculationType;
use Greendot\EshopBundle\Repository\Project\HandlingPriceRepository;
use Greendot\EshopBundle\Repository\Project\VoucherRepository;
use Greendot\EshopBundle\Service\CzechPostParcel;
use Greendot\EshopBundle\Service\PacketeryParcel;
use Greendot\EshopBundle\Service\PurchaseApiModel;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Currency;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Form\ClientFormType;
use Greendot\EshopBundle\Repository\Project\PaymentTypeRepository;
use Greendot\EshopBundle\Repository\Project\ProductVariantRepository;
use Greendot\EshopBundle\Repository\Project\TransportationRepository;
use Greendot\EshopBundle\Service\GPWebpay;
use Greendot\EshopBundle\Service\PriceCalculator;
use Greendot\EshopBundle\Service\ManageVoucher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Workflow\Registry;

class PurchaseController extends AbstractController
{
    #[Route('/api/client/submit', name: 'api_client_submit', methods: ['POST'])]
    public function submitClientForm(
        Request                  $request,
        GPWebpay                 $GPWebpay,
        EntityManagerInterface   $entityManager,
        SessionInterface         $session,
        Registry                 $workFlow,
        PriceCalculator          $priceCalculator,
    ): Response|JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $loggedInUser = $this->getUser();

        if ($loggedInUser) {
            $client = $loggedInUser;
        } else {

            $form = $this->createForm(ClientFormType::class, null, [
                'csrf_protection' => false
            ]);

            $form->submit($data);

            if (!$form->isValid()) {
                $errorMessages = [];
                foreach ($form->getErrors(true) as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
            }

            $client = $form->getData();
            $entityManager->persist($client);
        }

        $clientAddresses = $client->getClientAddresses();
        if ($clientAddresses->isEmpty()) {
            $clientAddress = new ClientAddress();
            $clientAddress->setClient($client);
            $client->addClientAddress($clientAddress);
        } else {
            $clientAddress = $clientAddresses->first();
        }

        $addressFields = [
            'street', 'city', 'zip', 'country', 'ic', 'dic',
            'ship_company', 'ship_name', 'ship_surname', 'ship_street',
            'ship_city', 'ship_zip', 'ship_country'
        ];

        foreach ($addressFields as $field) {
            $setter = 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $field)));
            if (method_exists($clientAddress, $setter) && isset($data[$field])) {
                $clientAddress->$setter($data[$field]);
            }
        }

        $entityManager->persist($clientAddress);
        $entityManager->flush();

        $newPurchase = $session->get('purchase');

        if (!$newPurchase) {
            return new Response('No order in session', 404);
        }

        $newPurchase->setClient($client);

        $purchaseFlow = $workFlow->get($newPurchase);

        if ($purchaseFlow->can($newPurchase, 'create')) {
            $purchaseFlow->apply($newPurchase, 'create');
        }

        $entityManager->persist($newPurchase);
        $entityManager->flush();

        if (!empty($data['order_note'])) {
            $note = new Note();
            $note->setText($data['order_note']);
            $note->setType('order');
            $note->setPurchase($newPurchase);

            $entityManager->persist($note);
            $entityManager->flush();
        }

        $currency = $session->get('selectedCurrency');

        $totalPrice = $priceCalculator->calculatePurchasePrice(
            $newPurchase,
            $currency,
            VatCalculationType::WithVAT,
            1,
            DiscountCalculationType::WithDiscount,
            true,
            VoucherCalculationType::WithoutVoucher,
            true
        );

        if ($purchaseFlow->can($newPurchase, 'receive')) {
            dump("can");
            $purchaseFlow->apply($newPurchase, 'receive');
            $entityManager->flush();
        }else{
            dump($purchaseFlow->buildTransitionBlockerList($newPurchase, 'receive'));
            dump("cant");
        }

        if ($newPurchase->getPaymentType()->getId() === 2) {
            $paymentUrl = $GPWebpay->getPayLink($newPurchase, $totalPrice);

            return new JsonResponse([
                'success'  => true,
                'redirect' => $paymentUrl
            ]);
        } else {
            return new JsonResponse([
                'success'  => true,
                'redirect' => $this->generateUrl('thank_you', ['id' => $newPurchase->getId()])
            ]);
        }
    }

    #[Route('/order/verify', name: 'shop_order_verify', methods: ['GET'])]
    public function verifyOrder(
        GPWebpay               $gpWebpay,
        EntityManagerInterface $entityManager,
        Registry               $workFlow,
    ): Response
    {
        $response = $gpWebpay->verifyLink();

        $paymentId = $response->getORDERNUMBER();

        $payment = $entityManager->getRepository(Payment::class)->find($paymentId);

        if (!$payment) {
            throw $this->createNotFoundException('Payment not found');
        }

        $purchase = $payment->getPurchase();

        if (!$purchase) {
            throw $this->createNotFoundException('Purchase not found');
        }

        $purchaseFlow = $workFlow->get($purchase);

        if ($response->getPRCODE() == '0' && $response->getSRCODE() == '0') {
            if ($purchaseFlow->can($purchase, 'payment')) {
                $purchaseFlow->apply($purchase, 'payment');
                $entityManager->flush();
            }

            return $this->redirectToRoute('thank_you', ['id' => $purchase->getId()]);
        } else {
            if ($purchaseFlow->can($purchase, 'payment_issue')) {
                $purchaseFlow->apply($purchase, 'payment_issue');
                $entityManager->flush();
            }

            return $this->redirectToRoute('thank_you', ['id' => $purchase->getId()]);
        }
    }

    #[Route('/api/applyVoucher', name: 'api_apply_voucher', options: ['expose' => true], methods: ['POST'])]
    public function applyVoucher(Request $voucherRequest, VoucherRepository $voucherRepository, PriceCalculator $priceCalculator, ManageVoucher $voucherValidator): JsonResponse
    {
        $couponCode = json_decode($voucherRequest->getContent())->couponCode;
        $couponType = json_decode($voucherRequest->getContent())->couponType;

        $hash = $voucherValidator->generateHash($couponCode);

        $voucher = $voucherRepository->findOneBy(['hash' => $hash]);

        if (!$voucher || !$voucherValidator->validateVoucher($voucher, $couponType)) {
            return new JsonResponse(['error' => 'Invalid coupon code'], 400);
        }

        $session  = $voucherRequest->getSession();
        $purchase = $session->get('purchase');
        $currency = $session->get('selectedCurrency');

        $priceWithoutVoucher = $priceCalculator->calculatePurchasePrice(
            $purchase,
            $currency,
            VatCalculationType::WithVAT,
            1,
            DiscountCalculationType::WithDiscount,
            true,
            VoucherCalculationType::WithoutVoucher,
            true
        );

        switch ($couponType) {
            case 'discountCoupon':
                $finalPrice = $priceCalculator->applyDiscount(
                    $voucher->getAmount(),
                    $priceWithoutVoucher,
                );
                break;
            case 'giftVoucher':
                $finalPrice = $priceCalculator->applyGiftVoucher(
                    $voucher->getAmount(),
                    $priceWithoutVoucher,
                );
                break;
            default:
                $finalPrice = $priceWithoutVoucher;
                break;
        }

        $purchase->addVoucherIssued($voucher);
        $session->set('purchase', $purchase);

        return new JsonResponse([
            'final_price'    => $finalPrice,
            'voucher_type'   => $voucher->getType(),
            'voucher_amount' => $voucher->getAmount()],
            200);
    }

    #[Route('/api/removeVoucher', name: 'api_remove_voucher', options: ['expose' => true], methods: ['POST'])]
    public function removeVoucher(
        Request           $request,
        SessionInterface  $session,
        PriceCalculator   $priceCalculator,
        VoucherRepository $voucherRepository
    ): JsonResponse
    {
        $data       = json_decode($request->getContent(), true);
        $couponType = $data['couponType'] ?? null;

        if (!$couponType) {
            return new JsonResponse(['error' => 'Coupon type is required'], 400);
        }

        $purchase = $session->get('purchase');
        $currency = $session->get('selectedCurrency');

        if (!$purchase) {
            return new JsonResponse(['error' => 'No purchase found in session'], 404);
        }

        $vouchersIssued = $purchase->getVouchersIssued();
        $voucherRemoved = false;

        foreach ($vouchersIssued as $voucher) {
            if ($voucher->getType() === $couponType) {
                $purchase->removeVoucherIssued($voucher);
                $voucherRemoved = true;
                break;
            }
        }

        if (!$voucherRemoved) {
            return new JsonResponse(['error' => 'No matching voucher found'], 404);
        }

        $session->set('purchase', $purchase);

        $priceWithoutVoucher = $priceCalculator->calculatePurchasePrice(
            $purchase,
            $currency,
            VatCalculationType::WithVAT,
            1,
            DiscountCalculationType::WithDiscount,
            true,
            VoucherCalculationType::WithoutVoucher,
            true
        );

        return new JsonResponse([
            'price_without_voucher' => $priceWithoutVoucher,
            'final_price'           => $priceWithoutVoucher,
        ], 200);
    }

    #[Route('/api/order', name: 'api_get_order', methods: ['GET'], options: ['expose' => true])]
    public function getOrder(SessionInterface $session, ProductVariantRepository $productVariantRepository): JsonResponse
    {
        $purchase = $session->get('purchase');

        if ($purchase != null) {
            foreach ($purchase->getProductVariants() as $orderProductVariant) {
                $productVariant       = $orderProductVariant->getProductVariant();
                $productVariantFromDB = $productVariantRepository->find($productVariant);
                $orderProductVariant->setProductVariant($productVariantFromDB);
            }
        }
        $purchaseApiModel = new PurchaseApiModel();
        $purchaseApiModel->parseEntity($purchase);

        return new JsonResponse($purchaseApiModel, 200);
    }

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

    #[Route('/api/calculate-variant-price', name: 'api_calculate_price', methods: ['POST'], options: ['expose' => true])]
    public function calculatePrice(Request $request, PriceCalculator $priceCalculator, ProductVariantRepository $repository, Session $session): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $variantId = $data['variant'];
        $amount    = $data['amount'];

        $productVariant         = $repository->find($variantId);
        $purchaseProductVariant = new PurchaseProductVariant();
        $purchase               = new Purchase();

        $purchaseProductVariant->setProductVariant($productVariant);
        $purchaseProductVariant->setAmount($amount);
        $purchaseProductVariant->setPurchase($purchase);

        $currency            = $session->get('selectedCurrency');
        $productVariantPrice = $priceCalculator->calculateProductVariantPrice(
            $purchaseProductVariant,
            $currency,
            VatCalculationType::WithVAT,
            DiscountCalculationType::WithDiscount,
            false,
            true
        );

        $productVariantPriceOld = $priceCalculator->calculateProductVariantPrice(
            $purchaseProductVariant,
            $currency,
            VatCalculationType::WithVAT,
            DiscountCalculationType::WithoutDiscount,
            false,
            true
        );

        if ($currency->getId() === 1) {
            $numericVariantPrice = str_replace(',', '.', $productVariantPrice);
            $productVariantPrice = round((float)$numericVariantPrice);

            $numericVariantPriceOld = str_replace(',', '.', $productVariantPriceOld);
            $productVariantPriceOld = round((float)$numericVariantPriceOld);
        }

        return new JsonResponse([
            'price'     => $productVariantPrice,
            'price_old' => $productVariantPriceOld
        ], 200);
    }

    #[Route('/api/calculate-purchase-price', name: 'api_calculate_purchase_price', methods: ['POST'], options: ['expose' => true])]
    public function calculatePurchasePrice(SessionInterface $session, PriceCalculator $priceCalculator): JsonResponse
    {
        $purchase = $session->get('purchase');
        $currency = $session->get('selectedCurrency');

        $purchasePrice = $priceCalculator->calculatePurchasePrice(
            $purchase,
            $currency,
            VatCalculationType::WithVAT,
            1,
            DiscountCalculationType::WithDiscount,
            true,
            VoucherCalculationType::WithoutVoucher,
            true
        );

        return new JsonResponse(['purchasePrice' => $purchasePrice], 200);
    }

    #[Route('/api/set-transportation', name: 'api_set_transportation', methods: ['POST'], options: ['expose' => true])]
    public function setTransportation(
        SessionInterface         $session,
        TransportationRepository $transportationRepository,
        Request                  $request,
        HandlingPriceRepository  $handlingPriceRepository): JsonResponse
    {
        $purchase = $session->get('purchase');
        $data     = json_decode($request->getContent(), true);

        $transportationId = $data['transportationId'] ?? null;
        $transportation   = $transportationRepository->find($transportationId);

        $transportationPrice = $handlingPriceRepository->GetByDate($transportation);

        $purchase->setTransportation($transportation);
        $session->set('purchase', $purchase);

        return new JsonResponse(['transportation_price' => $transportationPrice->getPrice()], 200);
    }

    #[Route('/api/set-payment', name: 'api_set_payment', methods: ['POST'], options: ['expose' => true])]
    public function setPayment(
        SessionInterface        $session,
        PaymentTypeRepository   $paymentTypeRepository,
        Request                 $request,
        HandlingPriceRepository $handlingPriceRepository
    ): JsonResponse
    {
        $purchase     = $session->get('purchase');
        $data         = json_decode($request->getContent(), true);
        $paymentId    = $data['paymentId'] ?? null;
        $paymentType  = $paymentTypeRepository->find($paymentId);
        $paymentPrice = $handlingPriceRepository->GetByDate($paymentType);

        $purchase->setPaymentType($paymentType);

        $session->set('purchase', $purchase);

        return new JsonResponse(['payment_price' => $paymentPrice->getPrice()], 200);
    }

    #[Route('/api/update-session-amount', name: 'api_update_session_amount', methods: ['POST'], options: ['expose' => true])]
    public function updateSessionAmount(Request $request, SessionInterface $session): JsonResponse
    {
        $data     = json_decode($request->getContent(), true);
        $itemId   = $data['variant'];
        $amount   = $data['amount'];
        $purchase = $session->get('purchase');

        if ($purchase) {
            foreach ($purchase->getProductVariants() as $orderProductVariant) {
                if ($orderProductVariant->getProductVariant()->getId() == $itemId) {
                    $orderProductVariant->setAmount($amount);
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

    #[Route('/api/purchase/{id}/create-parcel', name: 'api_purchase_create_parcel', methods: ['POST'])]
    public function createParcel(
        Purchase               $purchase,
        CzechPostParcel        $czechPostParcel,
        EntityManagerInterface $entityManager
    ): JsonResponse
    {
        $parcelId = $czechPostParcel->createParcel($purchase);

        if (!$parcelId) {
            return new JsonResponse(['message' => 'Failed to create parcel'], 500);
        }

        $purchase->setTransportNumber($parcelId);
        $entityManager->flush();

        return new JsonResponse([
            'message'  => 'Parcel created successfully',
            'parcelId' => $parcelId
        ]);
    }

    #[Route('/api/purchase/{id}/parcel-status', name: 'api_purchase_parcel_status', methods: ['GET'])]
    public function getParcelStatus(
        Purchase        $purchase,
        CzechPostParcel $czechPostParcel
    ): JsonResponse
    {
        $status = $czechPostParcel->getParcelStatus($purchase);

        if (!$status) {
            return new JsonResponse(['message' => 'Failed to get parcel status'], 500);
        }

        return new JsonResponse($status);
    }

    #[Route('/objednavka/obsah', name: 'shop_order_first', options: ['expose' => true])]
    public function first(): Response
    {
        return $this->render('shop/cart/step1.html.twig');
    }

    #[Route('/objednavka/doprava', name: 'shop_order_second', options: ['expose' => true])]
    public function second(): Response
    {
        return $this->render('shop/cart/step2.html.twig');
    }

    #[Route('/objednavka/platba', name: 'shop_order_third', options: ['expose' => true])]
    public function third(): Response
    {
        return $this->render('shop/cart/step3.html.twig');
    }

    #[Route('/objednavka-dokoncena/{id}', name: 'thank_you', priority: 3)]
    public function thankYou($id, SessionInterface $session): Response
    {
        $orderDate = (new \DateTime())->modify('+2 weeks')->format('j.n.Y');

        $session->remove('purchase');

        return $this->render('thank-you-pages/thank-you-cart.html.twig', [
            'orderId'   => $id,
            'orderDate' => $orderDate
        ]);
    }

    #[Route('/api/ares-{ic}', name: 'ares_api')]
    public function aresTest($ic): JsonResponse
    {
        if (!$ic || strlen($ic) != 8 || !preg_match('/^\d+$/', $ic)) {
            return new JsonResponse(['statusText' => 'Špatný formát IČO']);
        }

        $aresEndpoint = "https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/{$ic}";

        $data = file_get_contents($aresEndpoint);

        if ($data === false) {
            return new JsonResponse(['statusText' => 'Požadovaná data nenalezena']);
        }

        $data = json_decode($data, true);

        if (!$data or !isset($data['sidlo']) or !isset($data["obchodniJmeno"])) {
            return new JsonResponse(['statusText' => 'Nebylo možné načíst data']);
        }

        $street = $data["sidlo"]["nazevUlice"] . " " . $data["sidlo"]["cisloDomovni"];

        if (isset($data["sidlo"]["cisloOrientacni"])) {
            $street .= "/" . $data["sidlo"]["cisloOrientacni"];
        }

        $return = [
            'city'    => $data["sidlo"]["nazevObce"],
            'street'  => $street,
            'zip'     => $data["sidlo"]["psc"],
            'company' => $data["obchodniJmeno"],
            'dic'     => $data["dic"] ?? null
        ];

        return new JsonResponse($return);
    }

    #[Route('/api/client/form', name: 'api_client_form', methods: ['GET'])]
    public function getClientForm(SerializerInterface $serializer): Response|JsonResponse
    {
        $form     = $this->createForm(ClientFormType::class, new Client());
        $formView = $form->createView();

        $formData = $serializer->serialize($formView, 'json');

        return new Response($formData, 200, ['Content-Type' => 'application/json']);
    }

    #[Route('/shop/api/session/currency-{currency}', name: "shop_api_session_currency")]
    public function shopApiSessionCurrency(Currency $currency)
    {
        return new JsonResponse([
            'symbol'          => $currency->getSymbol(),
            'name'            => $currency->getName(),
            'rounding'        => $currency->getRounding(),
            'conversion_rate' => $currency->getConversionRate()
        ], 200);
    }
}
