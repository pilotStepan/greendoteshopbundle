<?php

namespace Greendot\EshopBundle\Controller\Simple;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Greendot\EshopBundle\Enum\Watchdog\WatchdogType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Greendot\EshopBundle\Repository\Project\ProductVariantRepository;
use Greendot\EshopBundle\Message\Watchdog\VariantAvailable\NotifyVariantAvailable;

#[Route('/simple/api/product_variants', name: 'simple_api_product_variants_')]
class SimpleProductVariantController extends AbstractController
{
    #[Route('/notify-watchdog', name: 'notify_watchdog', methods: ['POST'])]
    public function notifyWatchdog(Request $request, MessageBusInterface $messageBus, ProductVariantRepository $variantRepository): Response
    {
        $data = json_decode($request->getContent(), true);

        $watchdogType = WatchdogType::tryFrom($data['watchdog_type']);
        if ($watchdogType === null) {
            return new Response('Unsupported watchdog type.', Response::HTTP_BAD_REQUEST);
        }

        $productVariantId = $data['product_variant_id'] ?? null;
        $productVariant = $variantRepository->find($productVariantId);
        if ($productVariant === null) {
            return new Response('Product variant not found.', Response::HTTP_BAD_REQUEST);
        }

        switch ($watchdogType) {
            case WatchdogType::VariantAvailable:
                $messageBus->dispatch(new NotifyVariantAvailable($data['product_variant_id']));
                break;
            default:
                return new Response('Unsupported watchdog type.', Response::HTTP_BAD_REQUEST);
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}