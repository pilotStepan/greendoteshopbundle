<?php

namespace Greendot\EshopBundle\Controller\Simple;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Greendot\EshopBundle\Message\ProductVariant\NotifyVariantAvailable;

#[Route('/simple/api/product_variants', name: 'simple_api_product_variants_')]
class SimpleProductVariantController extends AbstractController
{
    public function __construct(private readonly MessageBusInterface $messageBus) {}

    #[Route('/notify-variant-available', name: 'notify_variant_available', methods: ['POST'])]
    public function notifyVariantAvailable(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $this->messageBus->dispatch(new NotifyVariantAvailable($data['product_variant_id']));

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}