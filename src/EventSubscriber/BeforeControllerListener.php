<?php

namespace Greendot\EshopBundle\EventSubscriber;

use App\Controller\Shop\OrderController;
use App\Controller\Shop\ProductController;
use App\Controller\TurnOffIsActiveFilterController;
use App\Controller\WebController;
use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use Greendot\EshopBundle\Repository\Project\CurrencyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class BeforeControllerListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Environment            $twig,
        private readonly CategoryRepository     $categoryRepository,
        private readonly CurrencyRepository     $currencyRepository,
    )
    {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $session = $event->getRequest()->getSession();

        if (!$session->has('selectedCurrency')) {
            $session->set(
                'selectedCurrency',
                $this->currencyRepository->findOneBy(['isDefault' => true])
            );
        }
    }

    /**
     * @param ControllerEvent $event
     * @return void
     */
    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;   // never touch the session in sub-requests
        }

        $controller = is_array($event->getController())
            ? $event->getController()[0]
            : $event->getController();

        $session = $event->getRequest()->getSession();

        if ($controller instanceof WebController) {
            $menu_categories = $this->categoryRepository->findMainMenuCategories();
            $this->twig->addGlobal('menu_categories', $menu_categories);
            $current_slug = $event->getRequest()->attributes->get("slug");

            if ($current_slug !== null) {
                $this->twig->addGlobal('current_slug', $current_slug);
            }
        }

        if ($controller instanceof WebController) {
            $this->twig->addGlobal('current_slug', '/');
        }

       if ($controller instanceof OrderController or $controller instanceof ProductController) {
           $order = $session->get('order');
           $productVariantsInCart = [];

           if ($order) {
               foreach ($order->getProductVariants() as $orderProductVariant) {
                   $productVariant = $orderProductVariant->getProductVariant();

                   if (array_key_exists($productVariant->getId(), $productVariantsInCart)) {
                       $count = $productVariantsInCart[$productVariant->getId()];
                       $count = $count+1;
                       $productVariantsInCart[$productVariant->getId()] = $count;
                   } else {
                       $productVariantsInCart[$productVariant->getId()] = 1;
                   }
               }
           }

           $this->twig->addGlobal('product_variant_occurrences', $productVariantsInCart);
       }

        if ($controller instanceof OrderController or $controller instanceof ProductController) {
            $order = $session->get('inquiry');
            $productVariantsInCart = [];

            if ($order) {
                foreach ($order->getProductVariants() as $orderProductVariant) {
                    $productVariant = $orderProductVariant->getProductVariant();

                    if (array_key_exists($productVariant->getId(), $productVariantsInCart)) {
                        $count = $productVariantsInCart[$productVariant->getId()];
                        $count = $count+1;
                        $productVariantsInCart[$productVariant->getId()] = $count;
                    } else {
                        $productVariantsInCart[$productVariant->getId()] = 1;
                    }
                }
            }

            $this->twig->addGlobal('product_variant_occurrences_inquiry', $productVariantsInCart);
        }

        if ($controller instanceof TurnOffIsActiveFilterController){
            $this->entityManager->getFilters()->disable('isActiveVariantFilter');
            $this->entityManager->getFilters()->disable('isActiveProductFilter');
        }
    }

    /**
     * @return string[]
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100],
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}
