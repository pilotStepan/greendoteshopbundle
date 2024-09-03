<?php

namespace Greendot\EshopBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TemplateController extends AbstractController
{
    #[Route('/template/{name}', name: 'template')]
    public function template($name): Response
    {
        return $this->render('template/'.$name.'.html.twig');
    }
    #[Route('/cart/step1', name: 'cart_step1')]
    public function cartStep1(): Response
    {
        return $this->render('template/cart/step1.html.twig');
    }
    #[Route('/cart/step2', name: 'cart_step2')]
    public function cartStep2(): Response
    {
        return $this->render('template/cart/step2.html.twig');
    }
    #[Route('/cart/step3', name: 'cart_step3')]
    public function cartStep3(): Response
    {
        return $this->render('template/cart/step3.html.twig');
    }

    #[Route('/client-section-template/index', name: 'client_section_index_template')]
    public function clientSectionIndex(): Response
    {
        return $this->render('template/client-section/index.html.twig');
    }
    #[Route('/client-section-template/orders', name: 'client_section_orders_template')]
    public function clientSectionOrders(): Response
    {
        return $this->render('template/client-section/orders.html.twig');
    }
    #[Route('/client-section-template/order/{id}', name: 'client_section_order_detail_template')]
    public function clientSectionOrderDetail(): Response
    {
        return $this->render('template/client-section/order-detail.html.twig');
    }

    #[Route('/client-section-template/personal', name: 'client_section_personal_template')]
    public function clientSectionPersonal(): Response
    {
        return $this->render('template/client-section/personal.html.twig');
    }
    #[Route('/client-section-template/settings', name: 'client_section_settings_template')]
    public function clientSectionSettings(): Response
    {
        return $this->render('template/client-section/settings.html.twig');
    }
}
