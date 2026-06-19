<?php

namespace Greendot\EshopBundle\Schema;

use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;
use Greendot\EshopBundle\Dto\BreadCrumb;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use Greendot\EshopBundle\Schema\Context\BreadcrumbSchemaContext;
use Greendot\EshopBundle\Schema\Context\ItemListSchemaContext;
use Greendot\EshopBundle\Entity\Project\Category as CategoryEntity;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(lazy: true)]
class SchemaTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly SchemaRegistry        $registry,
        private readonly CategoryRepository    $categoryRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack          $requestStack,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_schemas', $this->registry->render(...), ['is_safe' => ['html']]),
            new TwigFunction('collect_schema', $this->registry->collect(...)),
            new TwigFunction('collect_breadcrumbs_schema', $this->collectBreadcrumbsSchema(...)),
            new TwigFunction('collect_item_list_schema', $this->collectItemListSchema(...)),
        ];
    }

    public function collectItemListSchema(CategoryEntity $category, int $itemsPerPage = 30): void
    {
        $page = max(1, (int)($this->requestStack->getMainRequest()?->query->get('page') ?? 1));
        $this->registry->collect(new ItemListSchemaContext($category, $page, $itemsPerPage));
    }

    public function collectBreadcrumbsSchema(array $crumbs): void
    {
        if (!empty($crumbs) && $crumbs[0] instanceof BreadCrumb) {
            // For new BreadCrumb[]
            $context = $this->urlGenerator->getContext();
            $base = $context->getScheme() . '://' . $context->getHost() . $context->getBaseUrl();
            $items = array_map(fn(BreadCrumb $c) => [
                'name' => $c->name,
                'url' => $base . $c->link,
            ], $crumbs);
        } else {
            // For legacy Entity[]
            $items = [];
            foreach ($crumbs as $crumb) {
                $items[] = [
                    'name' => $crumb->getName(),
                    'url' => $this->urlGenerator->generate('app_master', ['slug' => $crumb->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                ];
            }
        }

        $homepage = $this->categoryRepository->find(1);
        $homepageItem = [
            'name' => $homepage->getName(),
            'url' => $this->urlGenerator->generate('web_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
        array_unshift($items, $homepageItem);

        $this->registry->collect(new BreadcrumbSchemaContext($items));
    }
}