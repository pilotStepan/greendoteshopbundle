<?php

namespace Greendot\EshopBundle\Tests\Entity;

use Greendot\EshopBundle\Entity\Project\PurchaseAddress;
use PHPUnit\Framework\TestCase;

class AddressDeliveryCompanyTest extends TestCase
{
    private function billingCompanyAddress(): PurchaseAddress
    {
        return (new PurchaseAddress())
            ->setCompany('Billing s.r.o.')
            ->setStreet('Opletalova 1402')
            ->setCity('Praha')
            ->setZip('11000')
            ->setCountry('cz');
    }

    public function testNoShippingAddress_usesBillingCompany(): void
    {
        $this->assertSame('Billing s.r.o.', $this->billingCompanyAddress()->getDeliveryCompany());
    }

    public function testDifferentShippingAddressWithoutCompany_returnsNull(): void
    {
        $address = $this->billingCompanyAddress()
            ->setShipStreet('Ruská 123')
            ->setShipCity('Mariánské Lázně')
            ->setShipZip('35301')
            ->setShipCountry('cz');

        $this->assertNull($address->getDeliveryCompany());
    }

    public function testShippingCompany_takesPrecedence(): void
    {
        $address = $this->billingCompanyAddress()
            ->setShipCompany('Shipping a.s.')
            ->setShipStreet('Ruská 123')
            ->setShipCity('Mariánské Lázně')
            ->setShipZip('35301')
            ->setShipCountry('cz');

        $this->assertSame('Shipping a.s.', $address->getDeliveryCompany());
    }
}
