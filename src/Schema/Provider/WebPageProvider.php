<?php

namespace App\Schema\Provider;

use Spatie\SchemaOrg\Schema;
use Spatie\SchemaOrg\BaseType;
use App\Schema\ObjectNotSupported;
use App\Schema\SchemaProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Greendot\EshopBundle\Entity\Project\Category as CategoryEntity;

class WebPageProvider implements SchemaProviderInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function supports(mixed $object): bool
    {
        return $object instanceof CategoryEntity;
    }

    public function provide(mixed $object): BaseType
    {
        if (!$object instanceof CategoryEntity) {
            throw new ObjectNotSupported();
        }

        $url = $this->urlGenerator->generate('app_master', ['slug' => $object->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);
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