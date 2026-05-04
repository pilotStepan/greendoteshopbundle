<?php

namespace Greendot\EshopBundle\Schema\Provider;

use Spatie\SchemaOrg\Schema;
use Spatie\SchemaOrg\WebPage;
use Greendot\EshopBundle\Schema\SchemaProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Greendot\EshopBundle\Schema\UnsupportedSchemaSubjectException;
use Greendot\EshopBundle\Entity\Project\Category as CategoryEntity;

class CategoryWebPageSchemaProvider implements SchemaProviderInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function supports(mixed $object): bool
    {
        return $object instanceof CategoryEntity;
    }

    public function provide(mixed $object): WebPage
    {
        if (!$this->supports($object)) {
            throw new UnsupportedSchemaSubjectException();
        }
        /** @var CategoryEntity $object */
        $url = $this->urlGenerator->generate(
            $object->getCategoryType()->getControllerName(),
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