<?php

namespace Greendot\EshopBundle\Tests\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Greendot\EshopBundle\Entity\Project\Purchase;
use PHPUnit\Framework\TestCase;

class PurchaseApiSecurityTest extends TestCase
{
    /** @var HttpOperation[] */
    private array $operations;

    protected function setUp(): void
    {
        $attrs = (new \ReflectionClass(Purchase::class))->getAttributes(ApiResource::class);
        $this->operations = $attrs[0]->getArguments()['operations'];
    }

    public function testGetItemRequiresOwnerOrAdmin(): void
    {
        $op = $this->findOperation(Get::class, null);
        self::assertNotNull($op, 'Default GET /purchases/{id} operation not found');
        self::assertStringContainsString("is_granted('ROLE_ADMIN')", $op->getSecurity());
        self::assertStringContainsString('object.getClient() == user', $op->getSecurity());
    }

    public function testGetCollectionRequiresAdmin(): void
    {
        $op = $this->findOperation(GetCollection::class, null);
        self::assertNotNull($op, 'Default GET /purchases collection not found');
        self::assertSame("is_granted('ROLE_ADMIN')", $op->getSecurity());
    }

    public function testDirectPostRequiresAdmin(): void
    {
        $op = $this->findOperation(Post::class, null);
        self::assertNotNull($op, 'Default POST /purchases not found');
        self::assertSame("is_granted('ROLE_ADMIN')", $op->getSecurity());
    }

    public function testSessionOperationsHaveNoSecurity(): void
    {
        self::assertNull(
            $this->findOperation(Get::class, '/purchases/session')?->getSecurity(),
            'GET /purchases/session must stay open for anonymous shoppers',
        );
        self::assertNull(
            $this->findOperation(GetCollection::class, '/purchases/session')?->getSecurity(),
            'GET collection /purchases/session must stay open',
        );
        self::assertNull(
            $this->findOperation(Patch::class, '/purchases/session')?->getSecurity(),
            'PATCH /purchases/session must stay open for anonymous shoppers',
        );
    }

    public function testCheckoutHasNoSecurity(): void
    {
        $op = $this->findOperation(Post::class, '/purchases/session/checkout');
        self::assertNotNull($op, 'POST /purchases/session/checkout not found');
        self::assertNull($op->getSecurity(), 'Checkout must be open for guest users');
    }

    private function findOperation(string $opClass, ?string $uriTemplate): ?HttpOperation
    {
        foreach ($this->operations as $op) {
            if ($op instanceof $opClass && $op->getUriTemplate() === $uriTemplate) {
                return $op;
            }
        }
        return null;
    }
}
