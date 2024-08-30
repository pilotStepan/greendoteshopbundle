<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Purchase;

class ValueAddedTaxCalculator
{
      ////////////////////////////////////////////////////////////////////////////////////
     /**  VRÁTÍ ČÁSTKU DANĚ V KORUNÁCH, ZAOKROUHLENOU NA DVĚ DESETINNÁ MÍSTA (HALÉŘE) **/
    ////////////////////////////////////////////////////////////////////////////////////
    public function getVat(int $price, int $vat): string
    {
        $tax_sum = $price * ($vat / 100);

        return round($tax_sum, 2) . ' Kč';
    }

      /////////////////////////////////////////////////////////////////////////////////////////////
     /** VRÁTÍ VÝŠI DAŇOVÉHO ZÁKLADU V KORUNÁCH, ZAOKROUHLENOU NA DVĚ DESETINNÁ MÍSTA (HALÉŘE) **/
    /////////////////////////////////////////////////////////////////////////////////////////////
    public function getNoVat(int $price, int $vat): string
    {
        $without_tax_sum = $price * (1 - ($vat / 100));

        return round($without_tax_sum, 2) . ' Kč';
    }

    public function getTotal(Purchase $order, bool $discount = true): float
    {
        $total_tax_sum = 0;

        if ($discount) {
            $total_tax_sum = $this->getDiscount($order, $total_tax_sum);
            $total_tax_sum = round($total_tax_sum);
        }


        /** TODO credit packages, subsciption, cycles
            foreach ($order->getCreditPackages() as $creditPackage) {
                $total_tax_sum += (float)$creditPackage->getPrice();
            }

            if ($order->getSubscription()) {
                $total_tax_sum += (float)$order->getSubscription()->getPrice();
            }

            foreach ($order->getCycles() as $cycle) {
                $total_tax_sum += (float)$cycle->getPrice();
            }
        **/

        return round($total_tax_sum, 2);
    }

    /**
     * projde všechny položky objednávky, na položkách s danou sazbou daně zavolá getVat a vrátí součet.
     * V případě vat=null pak projde všechno a vrátí sum se slevou.
     *
     * @param Purchase $order
     * @param int $vat
     * @param bool $discount
     */
    public function getTotalVat(Purchase $order, int $vat = null, bool $discount = true): float
    {
        $total_tax_sum = 0;

        /** TODO TODO credit packages, subsciption, cycles

            foreach ($order->getCreditPackages() as $creditPackage) {
                if ($creditPackage->getVat() == $vat || $vat === null) {
                    $total_tax_sum += (float)$this->getVat($creditPackage->getPrice(), $creditPackage->getVat());
                }
            }

            foreach ($order->getCycles() as $cycle) {
                if ($cycle->getVat() == $vat || $vat === null) {
                    $total_tax_sum += (float)$this->getVat($cycle->getPrice(), $cycle->getVat());
                }
            }

            if ($order->getSubscription()) {
                if ($order->getSubscription()->getVat() == $vat || $vat === null) {
                    $total_tax_sum += (float)$this->getVat($order->getSubscription()->getPrice(), $order->getSubscription()->getVat());
                }
            }
        **/

        if ($discount) {
            $total_tax_sum = $this->getDiscount($order, $total_tax_sum);
            $total_tax_sum = round($total_tax_sum);
        }

        return round($total_tax_sum, 2);
    }
      ////////////////////////////////////////////////////////////////////////////////////////////////
     /** projde všechny položky, na položkách s danou sazbou daně zavolá getNoVat a vrátí součet. **/
    ////////////////////////////////////////////////////////////////////////////////////////////////
    public function getTotalNoVat(Purchase $order, int $vat, bool $discount = true): float
    {
        $without_tax_total_sum = 0;

        /** TODO credit, cycles, subscription
        foreach ($order->getCreditPackages() as $creditPackage) {
                if ($creditPackage->getVat() == $vat) {
                    $without_tax_total_sum += (float)$this->getNoVat($creditPackage->getPrice(), $creditPackage->getVat());
                }
            }
            foreach ($order->getCycles() as $cycle) {
                if ($cycle->getVat() == $vat) {
                    $without_tax_total_sum += (float)$this->getNoVat($cycle->getPrice(), $cycle->getVat());
                }
            }//
            if ($order->getSubscription()) {
                if ($order->getSubscription()->getVat() == $vat) {
                    $without_tax_total_sum += (float)$this->getNoVat($order->getSubscription()->getPrice(), $order->getSubscription()->getVat());
                }
            }
        **/

        if ($discount) {
            $without_tax_total_sum = $this->getDiscount($order, $without_tax_total_sum);
            $without_tax_total_sum = round($without_tax_total_sum);
        }

        return round($without_tax_total_sum, 2);
    }

    public function getDiscount(Purchase $order, float $price): float
    {
        $reducedPrice = $price;

        /** TODO client discount
            if ($order->getClient()->getDiscountGroup()) {
                $group = $order->getClient()->getDiscountGroup();
                $reducedPrice = $reducedPrice * (100 - $group->getDiscount()) / 100;
            } elseif ($order->getDiscountCode()) {
                $code = $order->getDiscountCode();
                $reducedPrice = $reducedPrice * (100 - $code->getDiscount()) / 100;
            }
        **/

        return $reducedPrice;
    }

    private function discountPriceByPercentage(float $price, float $discount): float
    {
        $discountedAmount = ($discount / 100) * $price;
        return $price - $discountedAmount;
    }
}
