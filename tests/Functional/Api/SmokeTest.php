<?php

namespace Greendot\EshopBundle\Tests\Functional\Api;

use Greendot\EshopBundle\Tests\App\ApiTestCase;

class SmokeTest extends ApiTestCase
{
    public function testContainerCompilesAndSchemaExists(): void
    {
        $this->assertTrue(self::getContainer()->has('doctrine.orm.entity_manager'));
        $this->getEntityManager()->getConnection()->executeQuery('SELECT 1 FROM product');
        $this->addToAssertionCount(1);
    }
}
