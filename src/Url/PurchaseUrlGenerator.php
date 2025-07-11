<?php

namespace Greendot\EshopBundle\Url;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class PurchaseUrlGenerator
{
    public function __construct(
        private UrlGeneratorInterface $router,
    ) {}

    /**
     * Absolute URL that shows the customer their order summary / thank-you page.
     */
    public function buildOrderDetailUrl(Purchase $purchase): string
    {
        $route = $purchase->getClient()->isIsAnonymous()
            ? 'thank_you'
            : 'client_section_order_detail';

        return $this->router->generate(
            $route,
            [
                'id' => $purchase->getId(),
                'created' => true,
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }
}