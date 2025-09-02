<?php

namespace Greendot\EshopBundle\Url;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

final readonly class PurchaseUrlGenerator
{
    public function __construct(
        private UrlGeneratorInterface     $router,
        #[Autowire(service: 'security.authenticator.login_link_handler.main')] 
        private LoginLinkHandlerInterface $loginLinkHandler,

    ) {}

    /**
     * Absolute URL that shows the customer their order summary / thank-you page.
     */
    public function buildOrderEndscreenUrl(Purchase $purchase): string
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
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }

    public function buildOrderDetailUrl(Purchase $purchase, bool $isCreated=false): string
    {
        $orderDetailUrl = $this->router->generate(
            'client_section_order_detail',
            ['id' => $purchase->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        if ($isCreated) {
            $separator = (parse_url($orderDetailUrl, PHP_URL_QUERY) ? '&' : '?');
            $orderDetailUrl .= $separator . 'created=1';
        }

        // If the client is registered, return the URL directly
        if (!$purchase->getClient()->isIsAnonymous()) return $orderDetailUrl;

        // If the client is anonymous, generate a login link
        $client = $purchase->getClient();
        $loginLinkDetails = $this->loginLinkHandler->createLoginLink($client);

        return $loginLinkDetails->getUrl() . '&redirect=' . urlencode($orderDetailUrl);
    }

    public function buildTrackingUrl(Purchase $purchase): ?string
    {
        if (!$purchase->getTransportNumber() || !$purchase->getTransportation()?->getStateUrl()) {
            return null;
        }

        $stateUrl = rtrim($purchase->getTransportation()->getStateUrl(), '/');
        $transportNumber = ltrim($purchase->getTransportNumber(), '/');

        return $stateUrl . '/' . $transportNumber;
    }
}