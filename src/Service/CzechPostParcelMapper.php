<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\Transportation;
use Greendot\EshopBundle\Entity\Project\Client;

class CzechPostParcelMapper
{
    public function mapPurchaseToParcelData(Purchase $purchase): array
    {
        $client = $purchase->getClient();
        $transportation = $purchase->getTransportation();
        $totalWeight = $this->calculateTotalWeight($purchase);

        return [
            'parcelServiceHeader' => $this->mapParcelServiceHeader($purchase, $transportation),
            'parcelServiceData' => [
                'parcelParams' => $this->mapParcelParams($purchase, $totalWeight),
                'parcelAddress' => $this->mapParcelAddress($client),
                'parcelServices' => $this->getParcelServices($purchase),
            ],
        ];
    }

    private function mapParcelServiceHeader(Purchase $purchase, Transportation $transportation): array
    {
        return [
            'parcelServiceHeaderCom' => [
                'transmissionDate' => $purchase->getDateIssue()->format('Y-m-d'),
                'customerID' => $transportation->getName(),
                'postCode' => $transportation->getCountry(),
                'locationNumber' => 1,
            ],
            'printParams' => [
                'idForm' => 101,
                'shiftHorizontal' => 0,
                'shiftVertical' => 0,
            ],
            'position' => 1,
        ];
    }

    private function mapParcelParams(Purchase $purchase, float $totalWeight): array
    {
        return [
            'recordID' => (string)$purchase->getId(),
            'prefixParcelCode' => 'DR',
            'weight' => number_format($totalWeight, 2),
            'insuredValue' => 0,
            'amount' => 0,
            'currency' => 'CZK',
            'vsVoucher' => $purchase->getInvoiceNumber(),
            'vsParcel' => $purchase->getTransportNumber() ?? '',
            'length' => 0,
            'width' => 0,
            'height' => 0,
        ];
    }

    private function mapParcelAddress(Client $client): array
    {
        return [
            'recordID' => (string)$client->getId(),
            'firstName' => $client->getName(),
            'surname' => $client->getSurname(),
            'company' => $client->getCompany() ?? '',
            'aditionAddress' => '',
            'subject' => $client->getCompany() ? 'P' : 'F',
            'address' => [
                'street' => $client->getStreet(),
                'houseNumber' => '',
                'sequenceNumber' => '',
                'cityPart' => '',
                'city' => $client->getCity(),
                'zipCode' => $client->getZip(),
                'isoCountry' => $client->getCountry(),
            ],
            'mobilNumber' => $client->getPhone(),
            'phoneNumber' => '',
            'emailAddress' => $client->getMail(),
        ];
    }

    private function getParcelServices(Purchase $purchase): array
    {
        $services = ['7']; ///////// Basic service

        // if ($purchase->getPaymentType()->getName() === 'Cash on Delivery') {
        //     $services[] = '41'; // <---- Cash on Delivery
        // }

        // parcel size (S, M, L, XL)

        $services[] = $this->getParcelSize($purchase);

        return $services;
    }

    private function getParcelSize(Purchase $purchase): string
    {
        $totalWeight = $this->calculateTotalWeight($purchase);


        /////////////////////////////////////////////////////////
        ///            TODO PARCEL SIZES s m l Xl atd
        //////////////////////////////////////////////////////

        if ($totalWeight <= 2) return 'S';
        if ($totalWeight <= 5) return 'M';
        if ($totalWeight <= 10) return 'L';
        return 'XL';
    }

    private function calculateTotalWeight(Purchase $purchase): float
    {

        /////////////////////////////////////////////////////////
        ///            TODO hmotnost baliku do db nebo nekde
        //////////////////////////////////////////////////////
        ///
        $totalWeight = 0;
        foreach ($purchase->getProductVariants() as $purchaseProductVariant) {
            $totalWeight += 1 /*$purchaseProductVariant->getProductVariant()->getWeight()*/ * $purchaseProductVariant->getAmount();
        }
        return $totalWeight;
    }
}