<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Notification\Handler;

use Greendot\EshopBundle\Service\ManageMails;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Greendot\EshopBundle\Mail\Factory\OrderDataFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Greendot\EshopBundle\Notification\PurchaseNotificationHandlerInterface;
use Greendot\EshopBundle\Notification\DescribableNotificationInterface;
use Greendot\EshopBundle\Notification\PreviewableEmailNotificationInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Greendot\EshopBundle\Workflow\Action\ActionCategory;
use Greendot\EshopBundle\Workflow\Action\ActionDescription;

#[AsTaggedItem(index: 'purchase_notification.company_email')]
final readonly class CompanyEmailHandler implements PurchaseNotificationHandlerInterface, DescribableNotificationInterface, PreviewableEmailNotificationInterface
{
    public function __construct(
        private ManageMails         $manageMails,
        private OrderDataFactory    $orderDataFactory,
        private TranslatorInterface $translator,
        #[Autowire(param: 'app.company_email')]
        private string              $companyEmail,
    ) {}

    public function describe(Purchase $purchase, string $transition): ActionDescription
    {
        return new ActionDescription('Poslat e-mail společnosti', detail: $this->companyEmail, category: ActionCategory::EMAIL);
    }

    public function buildEmail(Purchase $purchase, string $transition): TemplatedEmail
    {
        $key = 'email.subject.order.company.' . $transition;
        $params = ['%id%' => $purchase->getOrderNumber() ?? ''];
        $subject = $this->translator->trans($key, $params, 'emails');
        if ($subject === $key) {
            $subject = $this->translator->trans('email.subject.order.company.default', $params, 'emails');
        }

        return $this->manageMails->getBaseTemplate()
            ->to($this->companyEmail)
            ->subject($subject)
            ->htmlTemplate(sprintf('email/order/company/%s.html.twig', $transition))
            ->context(['data' => $this->orderDataFactory->create($purchase), 'transition' => $transition])
        ;
    }

    public function handle(Purchase $purchase, string $transition): void
    {
        $this->manageMails->sendTemplate($this->buildEmail($purchase, $transition));
    }
}
