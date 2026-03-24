<?php

namespace Greendot\EshopBundle\StructuredData\Provider\Default;

use Greendot\EshopBundle\StructuredData\Contract\StructuredDataProviderInterface;
use Greendot\EshopBundle\StructuredData\Model\BreadcrumbContext;
use Greendot\EshopBundle\StructuredData\Model\BreadcrumbList;
use Greendot\EshopBundle\StructuredData\Model\ListItem;

use Greendot\EshopBundle\Entity\Project\Category;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Provides structured data for breadcrumbs.
 */
class DefaultBreadcrumbProvider implements StructuredDataProviderInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator
    ) {}

    public function supports(mixed $object): bool
    {
        if ($object instanceof BreadcrumbContext) {
            return true;
        }

        return false;
    }

    public function provide(mixed $object): object|array|null
    {
        assert($object instanceof BreadcrumbContext);

        $list = new BreadcrumbList();
        $listItems = [];
        $position = 1;

        foreach ($object->getItems() as $item) {
            $listItem = new ListItem();
            $listItem->setPosition($position++);
            $listItem->setName(strip_tags($item['name'] ?? ''));
            $listItem->setItem($item['url']);
            $listItems[] = $listItem;
        }

        $list->setItemListElement($listItems);

        return $list;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
