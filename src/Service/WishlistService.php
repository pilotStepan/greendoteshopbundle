<?php

namespace Greendot\EshopBundle\Service;

use InvalidArgumentException;
use Nzo\UrlEncryptorBundle\Encryptor\Encryptor;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Enum\VatCalculationType;
use Greendot\EshopBundle\Service\Price\PurchasePriceFactory;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Service\Price\ProductVariantPriceFactory;

readonly class WishlistService
{
    public function __construct(
        private CurrencyResolver           $currencyResolver,
        private PurchasePriceFactory       $purchasePriceFactory,
        private ProductVariantPriceFactory $productVariantPriceFactory,
        private CurrencyRepository         $currencyRepository,
        private Encryptor                  $encryptor,
        private PurchaseRepository         $purchaseRepository,
    ) {}

    public function generateUrlToken(Purchase $wishlist): string
    {
        $data = sprintf('%d:%d', $wishlist->getId(), $wishlist->getClient()->getId());
        return $this->encryptor->encrypt($data);
    }

    public function getFromUrlToken(string $token): Purchase
    {
        [$wishlistId, $clientId] = explode(':', $this->encryptor->decrypt($token));
        $wishlist = $this->purchaseRepository->findOneBy(['id' => $wishlistId, 'client' => $clientId]);

        if (!$wishlist) {
            throw new InvalidArgumentException('Wishlist not found');
        }

        return $wishlist;
    }

    public function preparePrices(Purchase $wishlist): Purchase
    {
        $main = $this->currencyRepository->findOneBy(['isDefault' => 1]);
        $secondary = $this->currencyRepository->findOneBy(['name' => 'Euro']);

        $priceCalc = $this->purchasePriceFactory->create($wishlist, $main, VatCalculationType::WithVAT);
        $totalWithVatMain = $priceCalc->getPrice();

        $priceCalc->setVatCalculationType(VatCalculationType::WithoutVAT);
        $totalNoVatMain = $priceCalc->getPrice();

        $priceCalc->setCurrency($secondary);
        $totalNoVatSecondary = $priceCalc->getPrice();

        $priceCalc->setVatCalculationType(VatCalculationType::WithVAT);
        $totalWithVatSecondary = $priceCalc->getPrice();

        $wishlist->setPrices([
            'total_with_vat_main' => $totalWithVatMain,
            'total_no_vat_main' => $totalNoVatMain,
            'total_with_vat_secondary' => $totalWithVatSecondary,
            'total_no_vat_secondary' => $totalNoVatSecondary,
        ]);

        $resolvedCurrency = $this->currencyResolver->resolve();
        foreach ($wishlist->getProductVariants() as $productVariant) {
            $productVariantPriceCalc = $this->productVariantPriceFactory->create(
                $productVariant,
                $resolvedCurrency,
                vatCalculationType: VatCalculationType::WithVAT,
            );
            $productVariant->setTotalPrice(
                $productVariantPriceCalc->getPrice(),
            );
        }

        return $wishlist;
    }
}