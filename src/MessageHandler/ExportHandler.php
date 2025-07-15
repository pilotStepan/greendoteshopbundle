<?php
namespace Greendot\EshopBundle\MessageHandler;

use Greendot\EshopBundle\Message\Export;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Greendot\EshopBundle\Service\Exports\ExportBase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ExportHandler
{
    public function __construct(
        private ServiceLocator $locator,
        private readonly CurrencyRepository $currencyRepository
    ){}

    public function __invoke(Export $export)
    {
        $className = $export->getExportClass();

        if (!is_subclass_of($className, ExportBase::class)){
            throw new \Exception('Invalid export class: ' . $className);
        }

        $exportInstance = $this->locator->get($className);
//        assert($exportInstance instanceof ExportGoogleProductFeed);
        if ($export->getLocale()){
            $exportInstance->setLocale($export->getLocale());
        }
        if ($export->getCurrencyId()){
            $currency = $this->currencyRepository->find($export->getCurrencyId());
            $exportInstance->setCurrency($currency);
        }

        $exportInstance->appendItem($export->getObjectId());
    }

}