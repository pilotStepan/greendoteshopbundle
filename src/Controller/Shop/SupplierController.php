<?php

namespace Greendot\EshopBundle\Controller\Shop;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Producer;
use Greendot\EshopBundle\Repository\Project\ProducerRepository;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Contracts\Translation\TranslatorInterface;

class SupplierController extends AbstractController
{
    public function __construct(
        private readonly TranslatorInterface $translator
    )
    {
    }

    #[Route('/{slug}', name: 'shop_supplier_list', priority: 2)]
    public function producerList(Category $category, ProducerRepository $producerRepository): Response
    {
        return $this->render('shop/supplier/suppliers.html.twig', [
            'suppliers' => $producerRepository->findAll(),
            'title'     => $category->getTitle(),
            'category'  => $category,
        ]);
    }

    #[Route('/znacky/{producerSlug}', name: 'shop_producer_products')]
    #[ParamConverter('producer', class: 'App\Entity\Project\Producer', options: ['mapping' => ['producerSlug' => 'slug']])]
    public function producerProducts(Producer $producer, ProductRepository $productRepository, SessionInterface $session): Response
    {
        $products = $productRepository->findProductsByProducer($producer->getId());

        $preparedProducts = array_map(function ($product) {
            $sizes  = [];
            $colors = [];

            foreach ($product->getProductVariants() as $variant) {
                foreach ($variant->getParameters() as $parameter) {
                    $parameterGroup = $parameter->getParameterGroup();
                    if ($parameterGroup) {
                        $parameterGroupName = $parameterGroup->getName();
                        if ($parameterGroupName === 'Velikost') {
                            $size = $parameter->getData();
                            if (!in_array($size, $sizes)) {
                                $sizes[] = $size;
                            }
                        } elseif ($parameterGroupName === 'Barva') {
                            $colorName = $parameter->getData();
                            $colorCode = $parameter->getData();
                            $colorData = [
                                'name' => $colorName,
                                'code' => $colorCode,
                            ];
                            if (!in_array($colorData, $colors)) {
                                $colors[] = $colorData;
                            }
                        }
                    }
                }
            }

//            $currencySymbol = $this->session->get('selectedCurrency');

            return [
                'id'                  => $product->getId(),
                'name'                => $product->getName(),
                'slug'                => $product->getSlug(),
                'mainImage'           => $product->getUpload() ? $product->getUpload()->getPath() : null,
                'availability'        => $product->getAvailability(),
                'priceCurrency'      => 'CZK',
//              'priceCurrencySymbol' => $currencySymbol,
                'priceCurrencySymbol' => 'Kč',
                'sizes'               => $sizes,
                'colors'              => $colors,
            ];
        }, $products);

        return $this->render('shop/producer/products.html.twig', [
            'title'    => $producer->getName(),
            'producer' => $producer,
            'products' => $preparedProducts,
        ]);
    }
}
