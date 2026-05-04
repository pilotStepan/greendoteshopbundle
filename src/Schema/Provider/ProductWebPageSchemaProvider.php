<?php

namespace Greendot\EshopBundle\Schema\Provider;

use Spatie\SchemaOrg\Schema;
use Spatie\SchemaOrg\WebPage;
use Greendot\EshopBundle\Schema\SchemaProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Greendot\EshopBundle\Entity\Project\Product as ProductEntity;
use Greendot\EshopBundle\Schema\UnsupportedSchemaSubjectException;

class ProductWebPageSchemaProvider implements SchemaProviderInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function supports(mixed $object): bool
    {
        return $object instanceof ProductEntity;
    }

    public function provide(mixed $object): WebPage
    {
        if (!$this->supports($object)) {
            throw new UnsupportedSchemaSubjectException();
        }
        /** @var ProductEntity $object */
        $url = $this->urlGenerator->generate(
            'shop_product',
            ['slug' => $object->getSlug()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        return Schema::webPage()
            ->identifier(sprintf('%s#webpage', $url))
            ->url($url)
            ->name($object->getName())
            ->description($object->getDescription())
        ;
    }

    public function getPriority(): int
    {
        return 0;
    }
}