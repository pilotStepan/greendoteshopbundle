<?php

namespace Greendot\EshopBundle\Controller\Shop;

use Greendot\EshopBundle\Entity\Project\ClientAddress;
use Greendot\EshopBundle\Entity\Project\Payment;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PurchaseProductVariant;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Enum\VoucherCalculationType;
use Greendot\EshopBundle\Form\ClientAddressType;
use Greendot\EshopBundle\Form\ClientChangePasswordFormType;
use Greendot\EshopBundle\Form\ClientFormType;
use Greendot\EshopBundle\Repository\Project\ClientRepository;
use Greendot\EshopBundle\Repository\Project\ProductVariantRepository;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Repository\Project\VoucherRepository;
use Greendot\EshopBundle\Service\CertificateMaker;
use Greendot\EshopBundle\Service\GPWebpay;
use Greendot\EshopBundle\Service\InvoiceMaker;
use Greendot\EshopBundle\Service\PriceCalculator;
use Greendot\EshopBundle\Service\QRcodeGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Workflow\Registry;


class ClientSectionController extends AbstractController
{
    private const ARES_ENDPOINT = "https://ares.gov.cz/ekonomicke-subjekty-v-be/rest/ekonomicke-subjekty/";

    #[IsGranted('ROLE_USER')]
    #[Route('/client/download-invoice/{orderId}', name: 'client_download_invoice')]
    public function downloadInvoice(
        int                    $orderId,
        InvoiceMaker           $invoiceMaker,
        EntityManagerInterface $entityManager,
        LoggerInterface        $logger
    ): Response
    {
        $purchaseRepository = $entityManager->getRepository(Purchase::class);
        $purchase           = $purchaseRepository->find($orderId);

        if (!$purchase || $purchase->getClient() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You do not have permission to access this invoice.');
        }

        try {
            $pdfFilePath = $invoiceMaker->createInvoiceOrProforma($purchase);

            if (!$pdfFilePath) {
                throw new \RuntimeException('Invoice generation failed');
            }

            if (!file_exists($pdfFilePath)) {
                throw new FileNotFoundException($pdfFilePath);
            }

            $response = new BinaryFileResponse($pdfFilePath);
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'faktura-' . $purchase->getId() . '.pdf'
            );

            return $response;
        } catch (\Exception $e) {
            $logger->error('Invoice download failed', [
                'orderId' => $orderId,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return $this->render('error/invoice-error.html.twig', [
                'orderId'      => $orderId,
                'errorMessage' => $e->getMessage(),
            ], new Response('', Response::HTTP_NOT_FOUND));
        }
    }

    #[Route('/zakaznik', name: 'client_section_index', priority: 2)]
    public function index(
        ClientRepository   $clientRepository,
        PurchaseRepository $purchaseRepository
    ): Response
    {
        if (!$user = $this->getUser()) return $this->redirectToRoute('web_homepage');
        if (!$client = $clientRepository->find($user)) return $this->redirectToRoute('web_homepage');

        $lastOrder = $purchaseRepository->lastPurchaseOfUser($client);

        return $this->render('client-section/index.html.twig', [
            'client' => $client,
            'lastOrder' => $lastOrder,
        ]);
    }

    #[Route('/zakaznik/platba/{purchaseID}', name: 'client_section_payment')]
    public function payment(
        int                $purchaseID,
        PurchaseRepository $purchaseRepository,
        QRcodeGenerator    $qrCodeGenerator,
        PriceCalculator    $priceCalculator,
        SessionInterface   $session
    ): Response
    {
        // TODO: validate if client is present and allowed to see this purchase
        $purchase = $purchaseRepository->find($purchaseID);
        $currency = $session->get('selectedCurrency');

        $totalPrice = $priceCalculator->calculatePurchasePrice(
            $purchase,
            $currency,
            VatCalculationType::WithVAT,
            1,
            DiscountCalculationType::WithDiscount,
            true,
            VoucherCalculationType::WithoutVoucher,
            true
        );

        $dueDate    = $purchase->getDateIssue()->modify('+14 days');
        $qrCodePath = $qrCodeGenerator->getUri($purchase, $dueDate);

        return $this->render('client-section/payment.html.twig', [
            'purchase'       => $purchase,
            'QRcode'         => $qrCodePath,
            'totalPrice'     => $totalPrice,
            'currencySymbol' => $currency->getSymbol(),
        ]);
    }

    #[Route('/zakaznik/platba-kartou/{purchaseID}', name: 'client_section_card_payment')]
    public function cardPayment(
        int                    $purchaseID,
        PurchaseRepository     $purchaseRepository,
        EntityManagerInterface $entityManager,
        PriceCalculator        $priceCalculator,
        GPWebpay               $GPWebpay,
        SessionInterface       $session
    ): RedirectResponse
    {
        // TODO: validate if client is present and allowed to see this purchase
        $purchase = $purchaseRepository->find($purchaseID);
        $currency = $session->get('selectedCurrency');

        if (!$purchase) {
            throw $this->createNotFoundException('Objednávka nenalezena');
        }

        $totalPrice = $priceCalculator->calculatePurchasePrice(
            $purchase,
            $currency,
            VatCalculationType::WithVAT,
            1,
            DiscountCalculationType::WithDiscount,
            true,
            VoucherCalculationType::WithoutVoucher,
            true
        );

        $payment = new Payment();
        $payment->setDate(new \DateTime());
        $payment->setPurchase($purchase);
        $payment->setAction($purchase->getPaymentType()->getActionGroup());
        $payment->setExternalId(1);

        $entityManager->persist($payment);
        $entityManager->flush();

        $paymentUrl = $GPWebpay->getPayLink($purchase, $payment->getId(), $totalPrice);

        return new RedirectResponse($paymentUrl);
    }

    #[Route('/zakaznik/objednavky', name: 'client_section_orders')]
    public function orders(
        ClientRepository   $clientRepository,
        PurchaseRepository $orderRepository,
        PaginatorInterface $paginator,
        Request            $request): Response
    {
        if (!$user = $this->getUser()) return $this->redirectToRoute('web_homepage');
        if (!$client = $clientRepository->find($user)) return $this->redirectToRoute('web_homepage');

        $orders = $orderRepository->getClientPurchases($client);
        $pagination = $paginator->paginate($orders, $request->query->getInt('page', 1), 5);
        $pagination->setTemplate('pagination/pagination.html.twig');

        return $this->render('client-section/orders.html.twig', [
            'orders'     => $orders,
            'pagination' => $pagination
        ]);
    }

    #[Route('/zakaznik/objednavka/{id}', name: 'client_section_order_detail')]
    public function order(
        int                $id,
        PurchaseRepository $purchaseRepository,
        QRcodeGenerator    $qrCodeGenerator,
        PriceCalculator    $priceCalculator,
        SessionInterface   $session): Response
    {
        // TODO: validate if client is present and allowed to see this purchase
        $purchase = $purchaseRepository->find($id);
        $currency = $session->get('selectedCurrency');

        $totalPrice = $priceCalculator->calculatePurchasePrice(
            $purchase,
            $currency,
            VatCalculationType::WithVAT,
            1,
            DiscountCalculationType::WithDiscount,
            true,
            VoucherCalculationType::WithoutVoucher,
            true
        );

        $dueDate    = $purchase->getDateIssue()->modify('+14 days');
        $qrCodePath = $qrCodeGenerator->getUri($purchase, $dueDate);

        return $this->render('client-section/order-detail.html.twig', [
            'purchase'       => $purchase,
            'QRcode'         => $qrCodePath,
            'totalPrice'     => $totalPrice,
            'currencySymbol' => $currency->getSymbol()
        ]);
    }

    #[Route('/zakaznik/zmena-udaju', name: 'client_section_personal')]
    public function profileDataChange(
        Request                $request,
        ClientRepository       $clientRepository,
        EntityManagerInterface $entityManager
    ): Response
    {
        if (!$user = $this->getUser()) return $this->redirectToRoute('web_homepage');
        if (!$client = $clientRepository->find($user)) return $this->redirectToRoute('web_homepage');

        $lastAddress = $client->getClientAddresses()->last() ?: new ClientAddress();

        $form        = $this->createForm(ClientFormType::class, $client);
        $addressForm = $this->createForm(ClientAddressType::class, $lastAddress);

        $form->handleRequest($request);
        $addressForm->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $addressForm->isSubmitted() && $addressForm->isValid()) {
            $entityManager->persist($client);

            if (!$lastAddress->getId()) {
                $lastAddress->setClient($client);
                $client->addClientAddress($lastAddress);
            }
            $lastAddress->setIsPrimary(true);
            $entityManager->persist($lastAddress);

            $entityManager->flush();

            $this->addFlash('success', 'Your profile has been updated.');
            return $this->redirectToRoute('client_section_personal');
        }

        return $this->render('client-section/personal.html.twig', [
            'form'        => $form->createView(),
            'addressForm' => $addressForm->createView(),
            'client'      => $client,
            'address'     => $lastAddress,
        ]);
    }

    #[Route('/zakaznik/nastaveni', name: 'client_section_settings')]
    public function settings(
        Request                     $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface      $entityManager,
        ClientRepository            $clientRepository): Response
    {
        $client = $clientRepository->find($this->getUser());

        $form = $this->createForm(ClientChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = $form->get('currentPassword')->getData();

            if ($passwordHasher->isPasswordValid($client, $currentPassword)) {
                $newPassword = $form->get('newPassword')->getData();
                $client->setPassword($passwordHasher->hashPassword($client, $newPassword));

                $entityManager->persist($client);
                $entityManager->flush();

                $this->addFlash('success', 'Vaše heslo bylo úspěšně změněno.');

                return $this->redirectToRoute('client_section_settings');
            } else {
                $this->addFlash('error', 'Současné heslo není správné.');
            }
        }

        return $this->render('client-section/settings.html.twig', [
            'form'   => $form->createView(),
            'client' => $client,
        ]);
    }

    #[Route('/api/calculate-purchase/{purchaseID}', name: 'api_calculate_purchase')]
    public function calculatePurchase(
        int                $purchaseID,
        PriceCalculator    $priceCalculator,
        PurchaseRepository $purchaseRepository,
        SessionInterface   $session): JsonResponse
    {
        $purchase = $purchaseRepository->find($purchaseID);
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

        $purchasePrice = (string)$purchasePrice . ' ' . $currency->getSymbol();

        return new JsonResponse(['purchasePrice' => $purchasePrice], 200);
    }

    #[Route('/api/calculate-variant/{variantID}/{amount}', name: 'api_calculate_variant')]
    public function calculateVariant(
        int                      $variantID,
        int                      $amount,
        PriceCalculator          $priceCalculator,
        ProductVariantRepository $repository,
        SessionInterface         $session): JsonResponse
    {
        $productVariant         = $repository->find($variantID);
        $purchaseProductVariant = new PurchaseProductVariant();
        $purchase               = new Purchase();

        $purchaseProductVariant->setProductVariant($productVariant);
        $purchaseProductVariant->setAmount($amount);
        $purchaseProductVariant->setPurchase($purchase);

        $currency = $session->get('currency');

        $productVariantPrice = $priceCalculator->calculateProductVariantPrice(
            $purchaseProductVariant,
            $currency,
            VatCalculationType::WithVAT,
            DiscountCalculationType::WithDiscount,
            false,
            true
        );

        if ($currency->getId() === 1) {
            $numericPrice        = str_replace(',', '.', $productVariantPrice);
            $productVariantPrice = round((float)$numericPrice);
        }

        $productVariantPrice = (string)$productVariantPrice . ' ' . $currency->getSymbol();

        return new JsonResponse([
            'variantPrice' => $productVariantPrice
        ], 200);
    }

    #[Route('/api/ares-{ico}', name: 'ares_api')]
    public function aresTest($ico): JsonResponse
    {
        if (!$ico || strlen($ico) != 8 || !preg_match('/^\d+$/', $ico)) {
            return new JsonResponse(['statusText' => 'Špatný formát IČO']);
        }

        $aresEndpoint = self::ARES_ENDPOINT . $ico;

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

    #[Route('/zakaznik/certifikaty', name: 'client_section_certificates')]
    public function certificates(
        ClientRepository $clientRepository,
        VoucherRepository $voucherRepository,
        Registry $workflowRegistry
    ): Response {
        if (!$user = $this->getUser()) return $this->redirectToRoute('web_homepage');
        if (!$client = $clientRepository->find($user)) return $this->redirectToRoute('web_homepage');

        $vouchers = $voucherRepository->findAllForClient($client);
        $voucherMetadata = [];

        // Get metadata for the vouchers
        foreach ($vouchers as $voucher) {
            $workflow = $workflowRegistry->get($voucher);
            $metadataStore = $workflow->getMetadataStore();

            $description = $metadataStore->getMetadata('description', $voucher->getState()) ?? 'No description';
            $short_description = $metadataStore->getMetadata('short_description', $voucher->getState()) ?? 'No short description';
            $class = $metadataStore->getMetadata('class', $voucher->getState()) ?? 'default-class';

            $voucherMetadata[$voucher->getId()] = [
                'state' => $voucher->getState(),
                'description' => $description,
                'short_description' => $short_description,
                'class' => $class,
            ];
        }

        return $this->render('client-section/certificates.html.twig', [
            'vouchers' => $vouchers,
            'voucherMetadata' => $voucherMetadata
        ]);
    }

    #[Route('/voucher/download/{id}', name: 'voucher_download')]
    public function downloadVoucher(
        int $id,
        VoucherRepository  $voucherRepository,
        CertificateMaker   $certificateMaker
    ): Response
    {
        $voucher = $voucherRepository->find($id);

        // check user login
        if ($voucher->getPurchaseIssued()->getClient() !== $this->getUser()) return $this->redirectToRoute('web_homepage');


        $pdfContent = $certificateMaker->createCertificate($voucher);

        return new Response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="voucher_' . $voucher->getId() . '.pdf"'
        ]);
    }


}
