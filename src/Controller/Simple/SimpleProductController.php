<?php

namespace Greendot\EshopBundle\Controller\Simple;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Greendot\EshopBundle\Algolia\ProductIndexQueue;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Greendot\EshopBundle\Message\Algolia\FlushProductIndex;

#[Route('/simple/api/products', name: 'simple_api_products_')]
class SimpleProductController extends AbstractController
{
    private const DEBOUNCE_SECONDS = 30;

    #[Route('/reindex', name: 'reindex', methods: ['POST'])]
    public function reindex(Request $request, MessageBusInterface $messageBus, ProductIndexQueue $queue, LoggerInterface $logger): Response
    {
        $data = json_decode($request->getContent(), true);

        $indexName = trim((string)($data['index'] ?? ''));
        if ($indexName === '') {
            return new Response('Missing "index".', Response::HTTP_BAD_REQUEST);
        }

        $ids = array_values(array_filter(array_map('intval', (array)($data['product_ids'] ?? []))));
        if (!$ids) {
            return new Response('Missing "product_ids".', Response::HTTP_BAD_REQUEST);
        }

        $logger->info('Received product reindex request.', [
            'index' => $indexName,
            'product_ids' => $ids,
        ]);

        $queue->add($indexName, $ids);

        if ($queue->claimFlush($indexName, self::DEBOUNCE_SECONDS)) {
            $messageBus->dispatch(new FlushProductIndex($indexName), [
                new DelayStamp(self::DEBOUNCE_SECONDS * 1000),
            ]);
        }

        return new Response('', Response::HTTP_NO_CONTENT);
    }
}
