<?php

namespace Greendot\EshopBundle\Factory\Project;

use Doctrine\Persistence\ObjectManager;
use Greendot\EshopBundle\Entity\Project\Client;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\Comment;
use Greendot\EshopBundle\Repository\Project\ClientRepository;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;


final class CommentFactory extends PersistentProxyObjectFactory
{

    public function __construct(private ObjectManager $manager)
    {
        parent::__construct();
    }

    public static function class(): string
    {
        return Comment::class;
    }

    protected function defaults(): array|callable
    {
        /** @var ProductRepository $productRepo */
        $productRepo = $this->manager->getRepository(Product::class);
        /** @var ClientRepository $clientRepo */
        $clientRepo = $this->manager->getRepository(Client::class);

        $products = $productRepo->findAll();
        $clients = $clientRepo->findBy(['isAnonymous' => false]);

        return [
            'content' => self::faker()->sentence(),
            'title' => self::faker()->words(3, true),
            'submitted' => self::faker()->dateTimeThisYear(),
            'isAdmin' => false,
            'isActive' => false,
            'isRead' => false,
            'product' => $products ? $products[array_rand($products)] : null,
            'client' => $clients ? $clients[array_rand($clients)] : null,
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this// ->afterInstantiate(function(Comment $comment): void {})
            ;
    }
}
