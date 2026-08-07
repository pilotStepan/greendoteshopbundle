<?php

namespace Greendot\EshopBundle\Schema\Provider;

use Spatie\SchemaOrg\Schema;
use Spatie\SchemaOrg\WebPage;
use Greendot\EshopBundle\Enum\CategoryTypeEnum;
use Greendot\EshopBundle\Schema\SchemaProviderInterface;
use Greendot\EshopBundle\Entity\Interface\PageableInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Greendot\EshopBundle\Entity\Project\Category as CategoryEntity;
use Greendot\EshopBundle\Schema\UnsupportedSchemaSubjectException;

class WebPageSchemaProvider implements SchemaProviderInterface
{
    private const HOMEPAGE_CATEGORY_ID = 1;

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
        $url = $this->resolveUrl($object);

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

    private function resolveUrl(PageableInterface $object): string
    {
        if ($object instanceof CategoryEntity) {
            if ($object->getId() === self::HOMEPAGE_CATEGORY_ID) {
                return $this->urlGenerator->generate('web_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);
            }

            if ($object->getCategoryType()?->getId() === CategoryTypeEnum::BLOG->value) {
                return $this->urlGenerator->generate(
                    'web_blog_detail',
                    ['slug' => $object->getSlug()],
                    UrlGeneratorInterface::ABSOLUTE_URL,
                );
            }
        }

        return $this->urlGenerator->generate(
            $object->getControllerName(),
            ['slug' => $object->getSlug()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}