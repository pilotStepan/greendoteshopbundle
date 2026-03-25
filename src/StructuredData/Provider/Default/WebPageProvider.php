<?php

namespace Greendot\EshopBundle\StructuredData\Provider\Default;

use Greendot\EshopBundle\StructuredData\Model\WebPage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Greendot\EshopBundle\Entity\Project\Category as CategoryEntity;
use Greendot\EshopBundle\StructuredData\Contract\StructuredDataProviderInterface;

class WebPageProvider implements StructuredDataProviderInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function supports(mixed $object): bool
    {
        return $object instanceof CategoryEntity;
    }

    /**
     * @param CategoryEntity $object
     */
    public function provide(mixed $object): WebPage
    {
        $url = $this->urlGenerator->generate('app_master', ['slug' => $object->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
        return (new WebPage())
            ->setId(sprintf('%s#webpage', $url))
            ->setUrl($url)
            ->setName($object->getName())
            ->setDescription($object->getDescription())
        ;
    }

    public function getPriority(): int
    {
        return 0;
    }
}