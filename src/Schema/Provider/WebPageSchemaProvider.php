<?php

namespace Greendot\EshopBundle\Schema\Provider;

use Spatie\SchemaOrg\Schema;
use Spatie\SchemaOrg\WebPage;
use Greendot\EshopBundle\Schema\SchemaProviderInterface;
use Greendot\EshopBundle\Entity\Interface\PageableInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Greendot\EshopBundle\Schema\UnsupportedSchemaSubjectException;

class WebPageSchemaProvider implements SchemaProviderInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function supports(mixed $object): bool
    {
        return $object instanceof PageableInterface;
    }

    public function provide(mixed $object): WebPage
    {
        if (!$this->supports($object)) {
            throw new UnsupportedSchemaSubjectException();
        }

        /** @var PageableInterface $object */
        $url = $this->urlGenerator->generate(
            $object->getControllerName(),
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