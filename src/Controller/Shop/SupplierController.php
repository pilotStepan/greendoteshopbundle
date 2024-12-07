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

    #[Route('/{slug}', name: 'shop_supplier_list')]
    public function producerList(Category $category, ProducerRepository $producerRepository): Response
    {
        return $this->render('shop/supplier/suppliers.html.twig', [
            'suppliers' => $producerRepository->findAll(),
            'title'     => $category->getTitle(),
            'category'  => $category,
        ]);
    }

    #[Route('/{slug}-v', name: 'shop_producer_products', priority: 2)]
    public function producerProducts(Producer $producer, ProductRepository $productRepository, SessionInterface $session): Response
    {


        return $this->render('shop/supplier/products.html.twig', [
            'title'    => $producer->getName(),
            'supplier' => $producer
        ]);
    }
}
