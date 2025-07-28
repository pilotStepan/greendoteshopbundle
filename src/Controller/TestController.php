<?php

namespace Greendot\EshopBundle\Controller;

use Greendot\EshopBundle\Service\ManageSms;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Greendot\EshopBundle\Entity\Project\ClientAddress;
use Greendot\EshopBundle\Mail\Factory\OrderDataFactory;
use Greendot\EshopBundle\Service\PaymentGateway\GPWebpay;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/test', name: 'app_test_')]
class TestController extends AbstractController
{
    #[Route('/', name: 'index', priority: '99')]
    public function testControl(): Response
    {
        return $this->render('test/index.html.twig', [
            'controller_name' => 'TestController',
        ]);
    }

    #[Route('/gpw', name: 'gpw')]
    public function gatewayTest(
        GPWebpay           $gateway,
        PurchaseRepository $purchaseRepository,
    ): Response
    {
        $purchase = $purchaseRepository->findOneBySession();
        $gatewayLink = $gateway->getPayLink($purchase);

        return new JsonResponse(['redirect' => $gatewayLink], 200);
    }

    #[Route('/sms', name: 'payment_gateway_verify')]
    public function sms(
        ManageSms $manageSms,
    ): Response
    {
        $purchase = (new Purchase())
            ->setClient((new Client())
                ->setPhone('+420 773 130 352')
                ->addClientAddress((new ClientAddress())
                    ->setIsPrimary(true)
                    ->setCountry('cz')
                )
            )
            ->setState('paid')
            ->setTransportNumber('TEST123456')
        ;
        $manageSms->sendOrderTransitionSms($purchase, 'payment');
        return new JsonResponse(['status' => 'SMS sent'], 200);
    }

    #[Route('/mails/purchases/{purchase}/transitions/{transition}/{raw}', name: 'mails_purchases_transitions')]
    public function mailsReceive(
        Purchase            $purchase,
        string              $transition,
        OrderDataFactory    $dataFactory,
        string              $raw = null,
    ): Response
    {
        $data = $dataFactory->create($purchase);
        if ($raw === '1') dd($data);

        $html = $this->renderView("email/order/$transition.html.twig", ['data' => $data]);

        return new Response($html);
    }
}
