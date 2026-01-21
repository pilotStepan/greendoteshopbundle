<?php

namespace Greendot\EshopBundle\Controller;

use Greendot\EshopBundle\Sms\ManageSms;
use Greendot\EshopBundle\Service\ManageMails;
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

    #[Route('/mails/purchases/{purchase}/transitions/{transition}/{mod}', name: 'mails_purchases_transitions')]
    public function mailsReceive(
        Purchase         $purchase,
        string           $transition,
        OrderDataFactory $dataFactory,
        string           $mod = null,
    ): Response
    {
        $data = $dataFactory->create($purchase);

        $mods = [];
        if ($mod !== null && trim($mod) !== '') {
            $mods = array_values(array_filter(array_map(
                static fn(string $m): string => strtolower(trim($m)),
                explode(',', $mod),
            ), static fn(string $m): bool => $m !== ''));
        }

        foreach ($mods as $m) {
            switch ($m) {
                case '$':
                    $data->orderPaid = true;
                    break;

                case 'vat':
                    $data->vatExempted = true;
                    break;

                case 'dd':
                    dd($data);

                default:
                    break;
            }
        }

        $html = $this->renderView("email/order/$transition.html.twig", ['data' => $data]);

        return new Response($html);
    }

    #[Route('/mails/send/{purchase}', name: 'mails_send')]
    public function mailsSend(Purchase $purchase, ManageMails $manageMails): Response
    {
        $manageMails->sendPurchaseDiscussionEmail($purchase);
        return new JsonResponse(['status' => 'Email sent'], 200);
    }
}
