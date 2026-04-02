<?php

namespace Greendot\EshopBundle\Affiliate;

use DateTime;
use Psr\Log\LoggerInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Cookie;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Greendot\EshopBundle\Enum\DiscountCalculationType;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;

readonly class AffiliateService
{
    public function __construct(
        private RequestStack         $requestStack,
        private PurchasePriceFactory $purchasePriceFactory,
        private CurrencyRepository   $currencyRepository,
        private MessageBusInterface  $messageBus,
        private LoggerInterface      $logger,
        private ?Connection          $affiliateDbConnection,
    ) {}

    // set affiliate data to purchase from cookies
    public function setAffiliateToPurchase(Purchase $purchase): void
    {
        if (!$this->isAffiliate()) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $purchase->setAffiliateId($request->cookies->get('affiliate_id'));
            $purchase->setAdId($request->cookies->get('ad_id'));
        }
    }

    // save affiliate data to cookies from request get params
    public function setAffiliateCookiesFromRequest(ResponseEvent $event): void
    {
        if (!$this->isAffiliate()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        if ($request->query->has('aff') && $request->query->has('rek')) {
            $affiliateId = $request->query->get('aff');
            $adId = (int)$request->query->get('rek');
            $cookieLifetime = 2592000; // 30 days in seconds

            $response->headers->setCookie(
                new Cookie('affiliate_id', $affiliateId, time() + $cookieLifetime, '/', null, false, true),
            );
            $response->headers->setCookie(
                new Cookie('ad_id', $adId, time() + $cookieLifetime, '/', null, false, true),
            );
        }
    }

    // send request to affiliate db to create an entry for purchase
    public function createAffiliateEntry(Purchase $purchase): void
    {
        if (!$this->isAffiliate()) {
            return;
        }

        $this->logger->info('Creating affiliate entry.', ['purchaseId' => $purchase->getId()]);

        $currency = $this->currencyRepository->findOneBy(['isDefault' => true]);
        $priceCalculator = $this->purchasePriceFactory->create($purchase, $currency, VatCalculationType::WithVAT, DiscountCalculationType::WithDiscount);
        $now = new DateTime();

        $data = [
            'castka' => $priceCalculator->getPrice() * 0.1,
            'cenaObj' => $priceCalculator->getPrice(),
            'FK_idobjednavky' => $purchase->getId(),
            'stav' => 1,
            'datum' => $now->getTimestamp(),
            'FK_idklient' => $purchase->getAffiliateId(),
            'FK_idvybery' => null,
            'FK_idreklama' => $purchase->getAdId(),
            'referer' => null,
            'datetime' => $now->format('Y-m-d H:i:s'),
        ];

        $this->affiliateDbConnection->insert('vydelky', $data);

    }

    // send request to affiliate db to cancel an entry for purchase
    public function CancelAffiliateEntry(Purchase $purchase): void
    {
        if (!$this->isAffiliate()) {
            return;
        }

        $this->logger->info('Canceling affiliate entry.', ['purchaseId' => $purchase->getId()]);

        // send request to db
        $this->affiliateDbConnection->update('vydelky', ['stav' => 4], ['FK_idobjednavky' => $purchase->getId()]);
    }

    public function dispatchCreateAffiliateEntryMessage(Purchase $purchase): void
    {
        if (!$this->isAffiliate() || !$this->purchaseIsAffiliate($purchase) || $this->affiliateEntryExists($purchase)) {
            return;
        }

        $this->messageBus->dispatch(
            new CreateAffiliateEntry(
                $purchase->getId(),
            ),
        );
    }

    public function dispatchCancelAffiliateEntryMessage(Purchase $purchase): void
    {
        if (!$this->isAffiliate() || !$this->purchaseIsAffiliate($purchase) || !$this->affiliateEntryExists($purchase)) {
            return;
        }

        $this->messageBus->dispatch(
            new CancelAffiliateEntry(
                $purchase->getId(),
            ),
        );
    }

    private function affiliateEntryExists(Purchase $purchase): bool
    {
        $exists = $this->affiliateDbConnection->fetchOne(
            'SELECT 1 FROM vydelky WHERE FK_idobjednavky = :id LIMIT 1',
            ['id' => $purchase->getId()],
        );

        return (bool)$exists;
    }

    private function isAffiliate(): bool
    {
        return ($this->affiliateDbConnection !== null);
    }

    private function purchaseIsAffiliate(Purchase $purchase): bool
    {
        return $purchase->getAffiliateId() and $purchase->getAdId();
    }
}