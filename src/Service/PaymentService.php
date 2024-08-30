<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Log;
use Greendot\EshopBundle\Entity\Project\PaymentAction;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Payment;
use Greendot\EshopBundle\Enum\LogType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\Registry;

class PaymentService
{
    private EntityManagerInterface $entityManager;
    private Registry $workflow;

    public function __construct(EntityManagerInterface $entityManager, Registry $workflow)
    {
        $this->entityManager = $entityManager;
        $this->workflow = $workflow;
    }

    public function downloadAndProcessPayments(\DateTimeInterface $startDate): void
    {
        $url = "https://online.rb.cz/ibs/eshop/payments-list?shopname=SMAZIKOVA&creditaccount=2583899001&creditbank=5500&password=j15iRiN02A&listtype=PLAIN&paidfrom=" . $startDate->format('d.m.Y');
        $csvData = file_get_contents($url);
        $dateNow = new \DateTime();
        $log = new Log();
        $log->setType(LogType::Bank->value)
            ->setDate($dateNow)
            ->setData($csvData);

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        $this->processCsvData($csvData, $dateNow);
    }

    private function processCsvData(string $csvData, \DateTime $dateNow): void
    {
        $lines = explode(PHP_EOL, $csvData);

        foreach ($lines as $line) {
            $columns = str_getcsv($line);
            if (count($columns) < 10) {
                continue;
            }

            $variableSymbol = $columns[9];
            $amount = floatval($columns[3]);

            $orderRepository = $this->entityManager->getRepository(Purchase::class);
            $purchase = $orderRepository->find($variableSymbol); // variabilni symbol je ID objednavky(?)

            if ($purchase && $purchase->getStatus() === 'received') {
                $orderAmount = floatval($purchase->getPrice());

                if (abs($orderAmount - $amount) <= 5) {
                    $purchaseFlow = $this->workflow->get($purchase);

                    if ($purchaseFlow->can($purchase, 'payment')) {
                        $purchaseFlow->apply($purchase, 'payment');
                        $this->entityManager->persist($purchase);
                    }

                    $actionTypeByTransfer = $this->entityManager->getRepository(PaymentAction::class)->findOneBy(['name' => 'platba převodem']);

                    $payment = new Payment();
                    $payment->setPurchase($purchase)
                        ->setDate($dateNow)
                        ->setAction($actionTypeByTransfer);
                    $this->entityManager->persist($payment);
                }
            }
        }

        $this->entityManager->flush();
    }
}
