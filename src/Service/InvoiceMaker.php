<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Repository\Project\SettingsRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Twig\Environment;
use Dompdf\Dompdf;
use Dompdf\Options;

class InvoiceMaker
{
    private const PDF_OUTPUT_DIR = '/public/receipts/';
    private const EXCEL_OUTPUT_DIR = 'receipts/';
    private const VAT_RATES = [10, 15, 21];

    public function __construct(
        private readonly Environment             $twig,
        private readonly ContainerInterface      $container,
        private readonly SettingsRepository      $settingsRepository,
        private readonly ValueAddedTaxCalculator $valueAddedTaxCalculator,
    ) {}

    public function createInvoiceOrProforma(Purchase $purchase): ?string
    {
        $invoiceData = $this->prepareInvoiceData($purchase);

        if (!$invoiceData) {
            return null;
        }

        $html = $this->renderHtml($invoiceData);
        $pdfFilePath = $this->generatePdf($html, $invoiceData['order_number']);
//        $this->generateExcel($invoiceData); FIXME: not ready yet

        return $pdfFilePath;
    }

    private function prepareInvoiceData(Purchase $purchase): ?array
    {
        $isInvoice = $purchase->getState() === 'paid';
        $isProforma = $purchase->getState() === 'received';

        if (!$isInvoice && !$isProforma) {
            return null;
        }

        $totalAmount = 0;
        foreach ($purchase->getProductVariants() as $purchaseProductVariant) {
            $totalAmount += $purchaseProductVariant->getAmount();
        }

        return [
            'order'           => $purchase,
            'is_invoice'      => $isInvoice,
            'is_proforma'     => $isProforma,
            'order_number'    => $purchase->getId(),
            'invoice_number'  => $isInvoice ? $purchase->getInvoiceNumber() : null,
            'created_at'      => $isInvoice ? $purchase->getDateInvoiced() : $purchase->getDateIssue(),
            'due_date'        => (clone($isInvoice ? $purchase->getDateInvoiced() : $purchase->getDateIssue()))->modify('+14 days'),
            'client'          => $purchase->getClient(),
            'payment_method'  => $purchase->getPaymentType()->getName(),
            'sum'             => $totalAmount
        ];
    }

    private function renderHtml(array $data): string
    {
        $template = $data['is_invoice'] ? 'pdf/invoice.html.twig' : 'pdf/proforma.html.twig';
//        $template = 'pdf/test.html.twig';
        return $this->twig->render($template, $data);
    }

    private function generatePdf(string $html, int $purchaseNumber): ?string
    {
        $pdfOptions = new Options();
        $pdfOptions->set('isRemoteEnabled', true);
        $pdfOptions->set('isHtml5ParserEnabled', true);
        $pdfOptions->setChroot(realpath('build'));

        $dompdf = new Dompdf($pdfOptions);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfFilePath = $this->container->getParameter('kernel.project_dir')
            . self::PDF_OUTPUT_DIR
            . 'faktura-'
            . $purchaseNumber
            . '.pdf';

        file_put_contents($pdfFilePath, $dompdf->output());

        return $pdfFilePath;
    }

    private function generateExcel(array $data): void
    {
        $spreadsheet = $this->createSpreadsheet($data);
        $this->populateSpreadsheet($spreadsheet, $data);
        $this->styleSpreadsheet($spreadsheet, $data);

        $writer = new Xlsx($spreadsheet);
        $writer->save(self::EXCEL_OUTPUT_DIR . 'faktura-' . $data['order_number'] . '.xlsx');
    }

    private function createSpreadsheet(array $data): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator("Yogashop")
            ->setTitle("Faktura č." . $data['order_number'])
            ->setSubject("Faktura č." . $data['order_number']);

        $spreadsheet->setActiveSheetIndex(0);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Faktura č.' . $data['order_number']);

        return $spreadsheet;
    }

    private function populateSpreadsheet(Spreadsheet $spreadsheet, array $data): void
    {
        $sheet = $spreadsheet->getActiveSheet();

        // Header
        $sheet->setCellValue('A1', 'Yogashop');
        $sheet->setCellValue('C1', $data['is_invoice'] ? "Faktura č.:" : "Proforma č.:");
        $sheet->setCellValue('D1', $data['is_invoice'] ? $data['invoice_number'] : $data['order_number']);

        // Dates
        $sheet->setCellValue('A2', "Vystaveno:");
        $sheet->setCellValue('B2', $data['created_at']->format("d.m.Y"));
        $sheet->setCellValue('C2', "Splatnost do:");
        $sheet->setCellValue('D2', $data['due_date']->format("d.m.Y"));

        // Supplier and Customer information
        $this->populateSupplierInfo($sheet, $data);
        $this->populateCustomerInfo($sheet, $data);

        // Payment method
        $sheet->setCellValue('A11', $data['is_invoice'] ? "Platební metoda:" : "Vybraná platební metoda:");
        $sheet->setCellValue('B11', $data['payment_method']);

        if ($data['is_proforma']) {
            $this->populateProformaInfo($sheet, $data);
        }

        // Items table
        $this->populateItemsTable($sheet, $data);

        // Totals
        $this->populateTotals($sheet, $data);
    }

    private function populateSupplierInfo(Worksheet $sheet, array $data): void
    {
        $sheet->setCellValue('A4', "Dodavatel");
        $sheet->setCellValue('A5', "Yogashop");
        $sheet->setCellValue('A6', "Poděbradova 699");
        $sheet->setCellValue('A7', "Praha 8, 182 00");
        $sheet->setCellValue('A8', "Česká republika");
        $sheet->setCellValue('A9', "IČO: 71531165");
    }

    private function populateCustomerInfo(Worksheet $sheet, array $data): void
    {
        $client = $data['client'];
        $clientAddresses = $client->getClientAddresses();
        $primaryAddress = $clientAddresses->isEmpty() ? null : $clientAddresses->first();

        $sheet->setCellValue('C4', "Odběratel");
        $sheet->setCellValue('C5', $client->getName() . " " . $client->getSurname());
        $sheet->setCellValue('C6', $client->getName());

        if ($primaryAddress) {
            $sheet->setCellValue('C7', $primaryAddress->getZip() . " " . $primaryAddress->getCity());
            $sheet->setCellValue('C8', $primaryAddress->getCountry() ?: "Česká republika");
        } else {
            $sheet->setCellValue('C7', "N/A");
            $sheet->setCellValue('C8', "Česká republika");
        }
    }

    private function populateProformaInfo(Worksheet $sheet, array $data): void
    {
        $sheet->setCellValue('A12', "Číslo účtu:");
        $sheet->setCellValue('B12', "5500/2583899001");
        $sheet->setCellValue('C12', "Variabilní symbol:");
        $sheet->setCellValue('D12', $data['order_number']);
    }

    private function populateItemsTable(Worksheet $sheet, array $data): void
    {
        $i = $data['is_proforma'] ? 14 : 13;
        $sheet->setCellValue('A' . $i, "Položka");
        $sheet->setCellValue('B' . $i, "Cena bez DPH");
        $sheet->setCellValue('C' . $i, "Výše DPH");
        $sheet->setCellValue('D' . $i, "Cena vč. DPH");
        $i++;

        $itemTypes = ['credit_packages', 'cycles', 'product_variants'];
        foreach ($itemTypes as $type) {
            if (isset($data[$type]) && is_iterable($data[$type])) {
                foreach ($data[$type] as $item) {
                    $this->populateItemRow($sheet, $i, $item);
                    $i++;
                }
            }
        }

        if (isset($data['subscription'])) {
            $this->populateItemRow($sheet, $i, $data['subscription']);
        }
    }

    private function populateItemRow(Worksheet $sheet, int $row, $item): void
    {
        $sheet->setCellValue('A' . $row, $item->getName());
        $sheet->setCellValue('B' . $row, $this->formatPrice($this->valueAddedTaxCalculator->getNoVat($item->getPrice(), $item->getVat())));
        $sheet->setCellValue('C' . $row, $item->getVat() . " %");
        $sheet->setCellValue('D' . $row, $this->formatPrice($item->getPrice()));
    }

    private function populateTotals(Worksheet $sheet, array $data): void
    {
        $highestRow = $sheet->getHighestRow();
        foreach (self::VAT_RATES as $rate) {
            $highestRow++;
            $sheet->setCellValue('A' . $highestRow, "Položky celkově s DPH {$rate}%");
            $sheet->setCellValue('B' . $highestRow, $this->formatPrice($this->valueAddedTaxCalculator->getTotalNoVat($data['order'], $rate)));
            $sheet->setCellValue('C' . $highestRow, "{$rate} %");
            $sheet->setCellValue('D' . $highestRow, $this->formatPrice($this->valueAddedTaxCalculator->getTotalNoVat($data['order'], $rate) + $this->valueAddedTaxCalculator->getTotalVat($data['order'], $rate)));
        }

        $highestRow++;
        $sheet->setCellValue('A' . $highestRow, "Celkově:");
        $sheet->setCellValue('B' . $highestRow, $this->formatPrice(array_sum(array_map(fn($rate) => $this->valueAddedTaxCalculator->getTotalNoVat($data['order'], $rate), self::VAT_RATES)))
        );
        $sheet->setCellValue('D' . $highestRow, $this->formatPrice($data['sum']));
    }

    private function styleSpreadsheet(Spreadsheet $spreadsheet, array $data): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();

        $sheet->getStyle('A1:D1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('C2:D2')->getFont()->setBold(true);
        $sheet->getStyle('A4:C4')->getFont()->setItalic(true);
        $sheet->getStyle('A5:C5')->getFont()->setBold(true);
        $sheet->getStyle('B11')->getFont()->setBold(true);

        if ($data['is_proforma']) {
            $sheet->getStyle('A12:D12')->getFont()->setItalic(true);
            $sheet->getStyle('B12:D12')->getFont()->setBold(true);
        }

        $tableStart = $data['is_proforma'] ? 14 : 13;
        $sheet->getStyle("A{$tableStart}:D{$tableStart}")->getFont()->setBold(true);

        $totalsStart = $highestRow - count(self::VAT_RATES);
        $sheet->getStyle("A{$totalsStart}:D{$highestRow}")->getFont()->setItalic(true);
        $sheet->getStyle("B{$highestRow}:D{$highestRow}")->getFont()->setBold(true);

        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $sheet->getStyle("A1:D{$highestRow}")->getAlignment()->setWrapText(true);

        $tableStyleArray = [
            'borders' => [
                'outline' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['argb' => 'FF000000'],
                ],
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ]
            ],
        ];
        $sheet->getStyle("A{$tableStart}:D{$highestRow}")->applyFromArray($tableStyleArray);
    }

    private function formatPrice(float $price): string
    {
        return number_format($price, 2, '.', ',') . " Kč";
    }
}
