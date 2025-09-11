<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\Registry;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;

readonly class PurchaseWishlistStateProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private PurchaseRepository     $purchaseRepository,
        private Registry               $workflowRegistry,
        private Security               $security,
        private RequestStack           $requestStack,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Purchase
    {
        $client = $this->security->getUser();
        if (!$client instanceof Client) {
            throw new HttpException(Response::HTTP_NO_CONTENT);
        }

        // Check if wishlist already exists in session or for the client, if not, create a new one
        $wishlist = $this->purchaseRepository->findWishlistBySession()
            ?: $this->purchaseRepository->findWishlistForClient($client)
                ?: $this->createWishlist($client);

        // Store wishlist ID in session
        $this->requestStack->getSession()->set('wishlist', $wishlist->getId());

        return $wishlist;
    }

    private function createWishlist(Client $client): Purchase
    {
        $wishlist = (new Purchase())
            ->setDateIssue(new \DateTime())
            ->setState('draft')
            ->setClient($client)
        ;

        $this->em->persist($wishlist);
        $this->em->flush();

        // Change purchase to 'wishlist' state after creation
        $workflow = $this->workflowRegistry->get($wishlist);
        if ($workflow->can($wishlist, 'create_wishlist')) {
            $workflow->apply($wishlist, 'create_wishlist');
            $this->em->flush();
        }

        return $wishlist;
    }
}