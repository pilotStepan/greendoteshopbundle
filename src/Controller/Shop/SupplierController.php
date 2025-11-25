<?php

namespace Greendot\EshopBundle\Controller\Shop;

use Greendot\EshopBundle\Entity\Project\Category;
use Greendot\EshopBundle\Entity\Project\Producer;
use Greendot\EshopBundle\Repository\Project\ProducerRepository;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Contracts\Translation\TranslatorInterface;

class SupplierController extends AbstractController
{
    #[Route('/{slug}', name: 'shop_supplier_list')]
    public function producerList(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Category $category,
        ProducerRepository $producerRepository): Response
    {
        return $this->render('shop/supplier/suppliers.html.twig', [
            'suppliers' => $producerRepository->findAll(),
            'title'     => $category->getTitle(),
            'category'  => $category,
        ]);
    }

    /**
     * requirements: ['slug' => '[a-z0-9\-]+'] to allow "-" in slug
     *
     * @param Producer $producer
     * @return Response
     */
    #[Route(
        path: '/{slug}-v',
        name: 'shop_producer_products',
        requirements: ['slug' => '[a-z0-9\-]+'],
        priority: 2)
    ]
    public function producerProducts(
        Producer $producer
    ): Response
    {


        return $this->render('shop/supplier/products.html.twig', [
            'title'    => $producer->getName(),
            'supplier' => $producer
        ]);
    }
}
