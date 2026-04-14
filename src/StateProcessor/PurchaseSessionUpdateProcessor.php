<?php

declare(strict_types=1);

namespace Greendot\EshopBundle\StateProcessor;

use Exception;
use LogicException;
use InvalidArgumentException;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use ApiPlatform\State\ProcessorInterface;
use Psr\Log\LoggerInterface;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\Cart\PurchaseUpdate;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class PurchaseSessionUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface     $validator,
        private LoggerInterface        $logger,
        private PurchaseUpdate         $purchaseUpdate,
    ) {}

    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): JsonResponse
    {
        $violations = $this->validator->validate($data, null, ['Default', 'patch']);
        if ($violations->count()) {
            $msg = (string) $violations;

            $this->logger->warning('Purchase session update validation failed', ['violations' => $msg]);

            return new JsonResponse(['errors' => [$msg]], 400);
        }

        try {
            $purchase = $this->em->getRepository(Purchase::class)->findOneBySession();
            if (!$purchase) {
                $this->logger->warning('Purchase session update failed: purchase not found for session');

                return new JsonResponse(['errors' => ['Košík nenalezen']], 400);
            }

            $this->em->wrapInTransaction(function () use ($data, $purchase) {
                $this->purchaseUpdate->applyFromInput($data, $purchase);
            });

            return new JsonResponse(null, 200);

        } catch (LogicException|InvalidArgumentException $e) {
            $this->logger->warning('Purchase session update rejected', ['error' => $e->getMessage()]);

            return new JsonResponse(['errors' => [$e->getMessage()]], 400);
        } catch (Exception|ORMException $e) {
            $this->logger->error('Purchase session update failed with unexpected exception', [
                'exceptionClass' => $e::class,
                'message'        => $e->getMessage(),
            ]);

            return new JsonResponse(['errors' => ['Došlo k neočekávané chybě']], 500);
        }
    }
}