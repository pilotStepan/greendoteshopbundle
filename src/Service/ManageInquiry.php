<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Note;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Repository\Project\ParameterGroupRepository;
use Greendot\EshopBundle\Repository\Project\ParameterRepository;
use Greendot\EshopBundle\Repository\Project\PaymentTypeRepository;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Repository\Project\TransportationRepository;
use Doctrine\Persistence\ManagerRegistry;
use Dompdf\Dompdf;
use MyCLabs\Enum\Enum;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class ManageInquiry
{
    function __construct(
        private readonly Environment              $templating,
        private readonly PriceCalculator          $priceCalculator,
        private readonly ManagerRegistry          $managerRegistry,
        private readonly ManagePurchase           $manageOrder,
        private readonly ManageMails              $manageMails,
        private readonly ParameterRepository      $parameterRepository,
        private readonly ParameterGroupRepository $parameterGroupRepository,
        private readonly PaymentTypeRepository    $paymentRepository,
        private readonly TransportationRepository $transportationRepository
    )
    {

    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    function createPDFInquiry(Purchase $order, $mail): void
    {
        $dompdf = new Dompdf(array('enable_remote' => true));
        $inquiryNumber = $this->manageOrder->generateInquiryNumber($order);
        $html = $this->templating->render(
            "pdf/inquiryPdf.html.twig",
            [
                'purchase' => $order,
                'mail' => $mail,
                'inquiry_number' => $inquiryNumber,
                'parameter_group_repository' => $this->parameterGroupRepository,
                'parameter_repository' => $this->parameterRepository
            ]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();
        $dompdf->stream('poptavka_' . $order->getId() . '.pdf');
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     * @throws TransportExceptionInterface
     */
    function sendPDFMail(Purchase $order, $mail, $notifyMail, $mailContent, $subject): void
    {
        $inquiryNumber = $this->manageOrder->generateInquiryNumber($order);
        $dompdf = new Dompdf(array('enable_remote' => true));
        $html = $this->templating->render("pdf/inquiryPdf.html.twig", ['purchase' => $order, 'mail' => $notifyMail, 'inquiry_number' => $inquiryNumber, 'parameter_group_repository' => $this->parameterGroupRepository, 'parameter_repository' => $this->parameterRepository,]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();
        $pdf = $dompdf->output();

        $this->manageMails->mailInquiry($inquiryNumber, $order, $mail, $pdf, $mailContent, $subject);
    }

    /**
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws \Exception
     */
    function createXLSInquiry(Purchase $order, $mail, $request): Response
    {
        $spreadsheet = new Spreadsheet();
        $sheetIntro = $spreadsheet->getActiveSheet();
        $currentDate = new \DateTime();
        $futureDate = clone $currentDate;
        $futureDate->modify('+1 month');


        $sheetIntro->setTitle("Poptávkový list");
        $sheetIntro->getCell("A1")->setValue("bdl.cz");
        $sheetIntro->getCell("C1")->setValue("Datum vytvoření: ");
        $sheetIntro->getCell("D1")->setValue($currentDate->format('d.m.Y'));
        $sheetIntro->getCell("E1")->setValue("Platnost do: ");
        $sheetIntro->getCell("F1")->setValue($futureDate->format('d.m.Y'));
        $spreadsheet->getProperties()->setTitle('Poptávka 12');

        $sheetIntro->getCell("B3")->setValue("Název produktu");
        $sheetIntro->getCell("C3")->setValue("Kód produktu");
        $sheetIntro->getCell("D3")->setValue("Skladovost");
        $sheetIntro->getCell("E3")->setValue("Množství");
        $sheetIntro->getCell("F3")->setValue("bez DPH");
        $sheetIntro->getCell("G3")->setValue("s DPH");

        $row = 4;

        foreach ($order->getProductVariants() as $purchaseProductVariant) {
            $productVariant = $purchaseProductVariant->getProductVariant();
            $sheetIntro->getCell("B" . $row)->setValue($productVariant->getName());

            $articleNumberGroup = $this->parameterGroupRepository->findOneBy(["name" => 'Article Number']);
            if ($articleNumberGroup) {
                $bdlCode = $this->parameterRepository->getParameterByDataAndProductVariant($articleNumberGroup, $productVariant)->getData();
            } else {
                $bdlCode = '';
            }

            $manufacturerNumberGroup = $this->parameterGroupRepository->findOneBy(["name" => 'Manufacturer Article Number']);
            $manufacturerCode = '';
            if ($manufacturerNumberGroup) {
                $manufacturerCode = $this->parameterRepository->getParameterByDataAndProductVariant($manufacturerNumberGroup, $productVariant);
                if ($manufacturerCode){
                    $manufacturerCode = $manufacturerCode->getData();
                }
            }

            $c = [];
            if ($bdlCode and $bdlCode != ''){
                $c []= "BDL: " . $bdlCode;
            }

            if ($manufacturerCode and $manufacturerCode != ''){
                $c []= "Výrobce: " . $manufacturerCode;
            }
            if ($c){
                $sheetIntro->getCell("C" . $row)->setValue(implode(', ', $c));
            }
            if ($productVariant->getStock() > 0) {
                $sheetIntro->getCell("D" . $row)->setValue($productVariant->getStock());
            } else {
                $sheetIntro->getCell("D" . $row)->setValue("Na dotaz");
            }
            $sheetIntro->getCell("E" . $row)->setValue($purchaseProductVariant->getAmount());
            $sheetIntro->getCell("F" . $row)->setValue($this->priceCalculator->calculateProductVariantPrice($productVariant, $request->getSession()->get("selectedCurrency"), VatCalculationType::WithoutVAT, DiscountCalculationType::WithDiscount) . " " . $request->getSession()->get("selectedCurrency")->getSymbol());
            $sheetIntro->getCell("G" . $row)->setValue($this->priceCalculator->calculateProductVariantPrice($productVariant, $request->getSession()->get("selectedCurrency"), VatCalculationType::WithVAT, DiscountCalculationType::WithDiscount) . " " . $request->getSession()->get("selectedCurrency")->getSymbol());

            $row++;
        }
        $sheetIntro->getCell("B" . $row)->setValue('Doprava - ' . $order->getTransportation()->getName());
        $sheetIntro->getCell("F" . $row)->setValue($this->priceCalculator->transportationPrice($order, VatCalculationType::WithoutVAT) . " " . $request->getSession()->get("selectedCurrency")->getSymbol());
        $sheetIntro->getCell("G" . $row)->setValue($this->priceCalculator->transportationPrice($order, VatCalculationType::WithVAT) . " " . $request->getSession()->get("selectedCurrency")->getSymbol());
        $row++;

        $row++;
        $sheetIntro->getCell("E" . $row)->setValue('Celková cena:');
        $sheetIntro->getCell("F" . $row)->setValue($this->priceCalculator->calculatePurchasePrice($order, $request->getSession()->get("selectedCurrency"), null, 1, VatCalculationType::WithoutVAT, DiscountCalculationType::WithDiscount) . " " . $request->getSession()->get("selectedCurrency")->getSymbol());
        $sheetIntro->getCell("G" . $row)->setValue('Bez DPH');
        $row++;

        $sheetIntro->getCell("E" . $row)->setValue('Celková cena:');
        $sheetIntro->getCell("F" . $row)->setValue($this->priceCalculator->calculatePurchasePrice($order, $request->getSession()->get("selectedCurrency"), null, 1, VatCalculationType::WithVAT, DiscountCalculationType::WithDiscount) . " " . $request->getSession()->get("selectedCurrency")->getSymbol());
        $sheetIntro->getCell("G" . $row)->setValue('S DPH');
        $row++;
        $sheetIntro->getColumnDimensionByColumn('2')->setAutoSize(true);
        $sheetIntro->getColumnDimensionByColumn('3')->setAutoSize(true);
        $sheetIntro->getColumnDimensionByColumn('4')->setAutoSize(true);
        $sheetIntro->getColumnDimensionByColumn('5')->setAutoSize(true);
        $sheetIntro->getColumnDimensionByColumn('6')->setAutoSize(true);

        if ($mail) {
            $sheetIntro->getCell("B" . $row)->setValue($mail . " si přeje být průběžně informován o stavu dodávky zboží.");
            $row++;
            $sheetIntro->getCell("B" . $row)->setValue("V případě, že budete vytvářet objednávku, napište do poznámky jeho e-mail, zařadíme ho na notifikační list.");
        }
        $row = $row + 2;

        $sheetIntro->getCell("B" . $row)->setValue("IČ: 27481441");
        $sheetIntro->getCell("C" . $row)->setValue("DIČ: CZ27481441");
        $sheetIntro->getCell("D" . $row)->setValue("Sídlo: Náměstí Českého ráje 2, 511 01 Turnov");
        $sheetIntro->getCell("F" . $row)->setValue("www.bdl.cz");

        $writer = new Xlsx($spreadsheet);
        $tempFilePath = tempnam(sys_get_temp_dir(), 'excel');
        $writer->save($tempFilePath);
        $response = new Response(file_get_contents($tempFilePath));
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $inquiryNumber = $this->manageOrder->generateInquiryNumber($order);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'poptavka_' . $order->getId() . '.xlsx'
        ));
        unlink($tempFilePath);
        return $response;
    }

    function saveInquiry(Purchase $order, $client, $note = null, $notifyMail = null)
    {
        //$order = new Purchase();
        $order->setClient($client);
        $order->setDateIssue(new \DateTime());
        $order->setInvoiceNumber(0);

        $transportation = $order->getTransportation();
        $payment = $order->getPaymentType();

        $transportation = $this->transportationRepository->find($transportation->getId());
        $payment = $this->paymentRepository->find($payment->getId());
        $order->setPaymentType($payment);
        $order->setTransportation($transportation);


        $this->managerRegistry->getManager()->persist($order);
        $this->managerRegistry->getManager()->flush();

        $order->setInvoiceNumber($order->getId());
        $this->managerRegistry->getManager()->persist($order);
        $this->managerRegistry->getManager()->flush();

        if ($note) {
            $newNote = new Note();
            $newNote->setPurchase($order);
            $newNote->setType('Poznámka');
            $newNote->setContent($note);
            $this->managerRegistry->getManager()->persist($newNote);
            $this->managerRegistry->getManager()->flush();
        }

        if ($notifyMail) {
            $newNote = new Note();
            $newNote->setPurchase($order);
            $newNote->setType('E-mail pro notifikace');
            $newNote->setContent($notifyMail);
            $this->managerRegistry->getManager()->persist($newNote);
            $this->managerRegistry->getManager()->flush();
        }

        return $order;
    }

    function saveClient($clientData): Client
    {
        $client = new Client();
        $client->setMail($clientData['mail']);
        $client->setCompany($clientData['company']);
        $client->setName($clientData['name']);
        $client->setSurname($clientData['surname']);
        $client->setIsVerified(false);
        $client->setIsAnonymous(1);
        $this->managerRegistry->getManager()->persist($client);
        $this->managerRegistry->getManager()->flush();
        return $client;
    }
}