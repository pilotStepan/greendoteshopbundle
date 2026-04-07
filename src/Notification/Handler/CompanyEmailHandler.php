<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Notification\Handler;

use Greendot\EshopBundle\Service\ManageMails;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Greendot\EshopBundle\Mail\Factory\OrderDataFactory;
use Greendot\EshopBundle\Attribute\AsPurchaseNotification;
use Greendot\EshopBundle\Notification\PurchaseNotificationHandlerInterface;

#[AsPurchaseNotification('company_email')]
final readonly class CompanyEmailHandler implements PurchaseNotificationHandlerInterface
{
    public function __construct(
        private ManageMails         $manageMails,
        private OrderDataFactory    $orderDataFactory,
        private TranslatorInterface $translator,
        private string              $companyEmail,
    ) {}

    public function handle(Purchase $purchase, string $transition): void
    {
        $orderData = $this->orderDataFactory->create($purchase);

        $key = 'email.subject.order.company.' . $transition;
        $params = ['%id%' => $purchase->getId() ?? ''];
        $subject = $this->translator->trans($key, $params, 'emails');
        if ($subject === $key) {
            $subject = $this->translator->trans('email.subject.order.company.default', $params, 'emails');
        }

        $email = $this->manageMails->getBaseTemplate()
            ->to($this->companyEmail)
            ->subject($subject)
            ->htmlTemplate(sprintf('email/order/company/%s.html.twig', $transition))
            ->context(['data' => $orderData, 'transition' => $transition])
        ;

        $this->manageMails->sendTemplate($email);
    }
}
