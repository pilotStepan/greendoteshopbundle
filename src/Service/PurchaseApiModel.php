<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Purchase;
use DateTimeZone;

class PurchaseApiModel
{
    public ?int $id;
    public ?int $payment;
    public ?int $transportation;
    public string $date_issue;
    public string $state;
    public array $product_variants = [];

    public ?array $voucher;

    public function parseEntity(Purchase $entity): void
    {
        $this->id         = $entity->getId();
        $this->date_issue = $entity->getDateIssue()->setTimezone(new DateTimeZone("UTC"))->format("Y-m-d\TH:i:s.v\Z");
        $this->state      = $entity->getState();

        foreach ($entity->getProductVariants() as $productVariant) {
            $parameters = [];
            foreach ($productVariant->getProductVariant()->getParameters() as $parameter) {
                if (in_array($parameter->getParameterGroup()->getName(), ['Barva', 'Velikost'])) {
                    $parameters[] = [
                        'data'           => $parameter->getData(),
                        'parameterGroup' => $parameter->getParameterGroup()->getName(),
                    ];
                }
            }

            $availability = $productVariant->getProductVariant()->getAvailability()->getName();
            $upload       = $productVariant->getProductVariant()->getUpload()->getPath();

            $this->product_variants[] = [
                'id'           => $productVariant->getProductVariant()->getId(),
                'name'         => $productVariant->getProductVariant()->getName(),
                'product_name' => $productVariant->getProductVariant()->getProduct()->getName(),
                'amount'       => $productVariant->getAmount(),
                'parameters'   => $parameters,
                'availability' => $availability,
                'upload'       => $upload,
            ];
        }

        if ($entity->getVouchersIssued()->count() > 0) {
            $voucher       = $entity->getVouchersIssued()[0];
            $this->voucher = [
                'amount' => $voucher->getAmount(),
                'state'  => $voucher->getState(),
                'type'   => $voucher->getType(),
            ];
        } else {
            $this->voucher = null;
        }

        $this->payment        = $entity->getPaymentType() ? $entity->getPaymentType()->getId() : null;
        $this->transportation = $entity->getTransportation() ? $entity->getTransportation()->getId() : null;
    }
}
