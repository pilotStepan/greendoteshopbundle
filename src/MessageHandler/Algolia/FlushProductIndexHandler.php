<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\MessageHandler\Algolia;

use Psr\Log\LoggerInterface;
use Monolog\Attribute\WithMonologChannel;
use Greendot\EshopBundle\Algolia\ProductIndexQueue;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Greendot\EshopBundle\Algolia\ProductIndexerInterface;
use Greendot\EshopBundle\Message\Algolia\FlushProductIndex;

#[AsMessageHandler]
#[WithMonologChannel('algolia')]
final class FlushProductIndexHandler
{
    public function __construct(
        private readonly ProductIndexQueue       $queue,
        private readonly ProductIndexerInterface $indexer,
        private readonly LoggerInterface         $logger,
    ) {}

    public function __invoke(FlushProductIndex $message): void
    {
        $ids = $this->queue->drain($message->indexName);

        if (!$ids) {
            $this->logger->debug('FlushProductIndex: nothing to do.', [
                'indexName' => $message->indexName,
            ]);
            return;
        }

        $indexed = $this->indexer->indexProducts($ids, $message->indexName);

        $this->logger->info('FlushProductIndex: flushed product batch.', [
            'indexName' => $message->indexName,
            'requested' => \count($ids),
            'indexed' => $indexed,
        ]);
    }
}
