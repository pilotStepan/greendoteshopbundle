<?php

namespace Greendot\EshopBundle\StateProvider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Purchase;
use Greendot\EshopBundle\Repository\Project\PurchaseRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Workflow\Registry;

readonly class PurchaseWishlistStateProvider implements ProviderInterface
{

    public function __construct(
        private EntityManagerInterface $em,
        private PurchaseRepository     $purchaseRepository,
        private Registry               $workflowRegistry,
        private Security               $security,
        private RequestStack           $requestStack,
    )
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        /* @var Client $user */
        // Ensure user is present
        if (!$user = $this->security->getUser()) return null;

        // Check if wishlist already exists in session or for the client, if not, create a new one
        $wishlist = $this->purchaseRepository->findWishlistBySession()
            ?: $this->purchaseRepository->findWishlistForClient($user)
                ?: $this->createWishlist($user);

        // Store wishlist ID in session
        $this->requestStack->getSession()->set('wishlist', $wishlist->getId());

        return $wishlist;
    }

    private function createWishlist(Client $user): Purchase
    {
        $wishlist = (new Purchase())
            ->setDateIssue(new \DateTime())
            ->setState('draft')
            ->setClient($user);

        $this->em->persist($wishlist);
        $this->em->flush();

        // Change purchase to 'wishlist' state after creation
        $workflow = $this->workflowRegistry->get($wishlist);
        if ($workflow->can($wishlist, 'create_wishlist')) {
            $workflow->apply($wishlist, 'create_wishlist');
        }

        return $wishlist;
    }
}