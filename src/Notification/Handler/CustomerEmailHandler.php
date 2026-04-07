<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Notification\Handler;

use Greendot\EshopBundle\Service\ManageMails;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Greendot\EshopBundle\Mail\Factory\OrderDataFactory;
use Greendot\EshopBundle\Attribute\AsPurchaseNotification;
use Greendot\EshopBundle\Notification\PurchaseNotificationHandlerInterface;

#[AsPurchaseNotification('customer_email')]
final readonly class CustomerEmailHandler implements PurchaseNotificationHandlerInterface
{
    public function __construct(
        private ManageMails         $manageMails,
        private OrderDataFactory    $orderDataFactory,
        private TranslatorInterface $translator,
    ) {}

    public function handle(Purchase $purchase, string $transition): void
    {
        $orderData = $this->orderDataFactory->create($purchase);

        $email = $this->manageMails->getBaseTemplate()
            ->to($purchase->getClient()->getMail())
            ->subject($this->resolveSubject($transition, $purchase->getId()))
            ->htmlTemplate($this->resolveHtmlTemplate($transition))
            ->context(['data' => $orderData, 'transition' => $transition])
        ;

        $this->manageMails->sendTemplate($email);
    }

    private function resolveSubject(string $transition, ?int $purchaseId): string
    {
        $params = ['%id%' => $purchaseId ?? ''];
        $key = 'email.subject.order.' . $transition;
        $translated = $this->translator->trans($key, $params, 'emails');

        if ($translated === $key) {
            $translated = $this->translator->trans('email.subject.order.default', $params, 'emails');
        }

        return $translated;
    }

    private function resolveHtmlTemplate(string $transition): string
    {
        return sprintf('email/order/%s.html.twig', $transition);
    }
}
