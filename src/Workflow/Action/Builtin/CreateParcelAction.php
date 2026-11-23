<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\Workflow\Action\Builtin;

use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\ManagePurchase;
use Greendot\EshopBundle\Parcel\ParcelServiceProviderInterface;
use Greendot\EshopBundle\Parcel\Exception\ParcelServiceNotFoundException;
use Greendot\EshopBundle\Workflow\Action\ActionCategory;
use Greendot\EshopBundle\Workflow\Action\ActionDescription;
use Greendot\EshopBundle\Workflow\Action\PurchaseActionInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * Creates the shipment at the carrier (async), when the purchase's transportation has a parcel integration.
 * CreateParcelHandler then polls the carrier and applies log_send / log_deliver from the parcel status.
 */
#[AsTaggedItem(index: self::KEY)]
final readonly class CreateParcelAction implements PurchaseActionInterface
{
    public const KEY = 'parcel.create';

    public function __construct(
        private ManagePurchase                 $managePurchase,
        private ParcelServiceProviderInterface $parcelServiceProvider,
    ) {}

    public function describe(Purchase $purchase, string $transition): ActionDescription
    {
        try {
            $parcelService = $this->parcelServiceProvider->getByPurchase($purchase);
        } catch (ParcelServiceNotFoundException) {
            return new ActionDescription('Zásilku založit ručně', detail: 'doprava nemá napojení na dopravce', category: ActionCategory::INTEGRATION);
        }

        // getByPurchase() succeeded, so the transportation and its API are set
        $carrier = $purchase->getTransportation()->getTransportationAPI()->carrierName();
        $transportNumber = $purchase->getTransportNumber();

        if (!$transportNumber) {
            return new ActionDescription('Založit zásilku', detail: $carrier, category: ActionCategory::INTEGRATION);
        }

        return new ActionDescription(
            'Zásilka už existuje',
            detail: sprintf(
                '%s %s, %s',
                $carrier,
                $transportNumber,
                $parcelService->supportsStatusPolling($purchase) ? 'stav se bude sledovat' : 'zadaná ručně, stav se nesleduje',
            ),
            category: ActionCategory::INTEGRATION,
        );
    }

    public function execute(Purchase $purchase, string $transition, array $context): void
    {
        $this->managePurchase->generateTransportData($purchase);
    }
}
