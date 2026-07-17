<?php

namespace Greendot\EshopBundle\Tests\Stub;

use Greendot\EshopBundle\Entity\Project\Payment;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Entity\Project\PaymentAction;
use Greendot\EshopBundle\Service\Payment\PaymentActionLogger;

readonly class RecordingPaymentActionLogger extends PaymentActionLogger
{
    public \ArrayObject $calls;

    public function __construct()
    {
        $this->calls = new \ArrayObject();
    }

    public function log(
        Purchase $purchase,
        string   $name,
        string   $performedBy,
        ?string  $description = null,
        array    $data = [],
        ?Payment $payment = null,
    ): PaymentAction
    {
        $this->calls->append([$purchase, $name, $performedBy, $description, $data, $payment]);

        return (new PaymentAction())
            ->setPurchase($purchase)
            ->setPayment($payment)
            ->setName($name)
            ->setPerformedBy($performedBy)
            ->setDescription($description)
            ->setData(json_encode($data));
    }
}
