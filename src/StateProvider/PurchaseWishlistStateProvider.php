<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Service\WishlistService;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Greendot\EshopBundle\Workflow\PurchaseWorkflowContract as PWC;

readonly class PurchaseWishlistStateProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private PurchaseRepository     $purchaseRepository,
        private Security               $security,
        private RequestStack           $requestStack,
        private WishlistService        $wishlistService,
        #[Target(PWC::NAME->value)]
        private WorkflowInterface      $purchaseFlow,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Purchase
    {
        $client = $this->security->getUser();
        if (!$client instanceof Client || $client->isIsAnonymous()) {
            throw new HttpException(Response::HTTP_NO_CONTENT);
        }

        // Check if wishlist already exists in session or for the client, if not, create a new one
        $wishlist = $this->purchaseRepository->findWishlistBySession()
            ?: $this->purchaseRepository->findWishlistForClient($client)
                ?: $this->createWishlist($client);

        $this->wishlistService->preparePrices($wishlist);

        // Store wishlist ID in session
        $this->requestStack->getSession()->set('wishlist', $wishlist->getId());

        return $wishlist;
    }

    private function createWishlist(Client $client): Purchase
    {
        $wishlist = (new Purchase())
            ->setDateIssue(new \DateTime())
            ->setClient($client)
        ;

        $this->purchaseFlow->apply($wishlist, PWC::T_INIT_WISHLIST->value);

        $this->em->persist($wishlist);
        $this->em->flush();

        return $wishlist;
    }
}