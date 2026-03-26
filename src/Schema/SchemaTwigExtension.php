<?php

namespace Greendot\EshopBundle\Schema;

use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;
use Greendot\EshopBundle\Context\BreadcrumbSchemaContext;
use Greendot\EshopBundle\Entity\Project\Category;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Greendot\EshopBundle\Repository\Project\CategoryRepository;

class SchemaTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly SchemaRegistry        $registry,
        private readonly CategoryRepository    $categoryRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_schemas', $this->registry->render(...), ['is_safe' => ['html']]),
            new TwigFunction('collect_schema', $this->registry->collect(...)),
            new TwigFunction('collect_breadcrumbs_schema', $this->collectBreadcrumbsSchema(...)),
        ];
    }

    /**
     * @param Category[] $crumbs
     */
    public function collectBreadcrumbsSchema(array $crumbs): void
    {
        $homepage = $this->categoryRepository->find(1);
        array_unshift($crumbs, $homepage);

        $items = [];
        foreach ($crumbs as $crumb) {
            $items[] = [
                'name' => $crumb->getName(),
                'url' => $this->urlGenerator->generate('app_master', ['slug' => $crumb->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
            ];
        }
        $this->registry->collect(new BreadcrumbSchemaContext($items));
    }
}