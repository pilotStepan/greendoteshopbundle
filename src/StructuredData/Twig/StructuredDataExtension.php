<?php

namespace Greendot\EshopBundle\StructuredData\Twig;

use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;
use Greendot\EshopBundle\Entity\Project\Category;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Greendot\EshopBundle\Repository\Project\CategoryRepository;
use Greendot\EshopBundle\StructuredData\Model\BreadcrumbContext;
use Greendot\EshopBundle\StructuredData\Service\StructuredDataManager;
use Greendot\EshopBundle\StructuredData\Service\StructuredDataRenderer;

/**
 * Twig extension for rendering structured data.
 */
class StructuredDataExtension extends AbstractExtension
{
    public function __construct(
        private StructuredDataManager  $manager,
        private StructuredDataRenderer $renderer,
        private UrlGeneratorInterface  $urlGenerator,
        private CategoryRepository     $categoryRepository,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_structured_data', $this->render(...), ['is_safe' => ['html']]),
            new TwigFunction('collect_structured_data', $this->collect(...)),
            new TwigFunction('collect_breadcrumbs', $this->collectBreadcrumbs(...)),
        ];
    }

    /**
     * Renders all collected structured data models.
     *
     * @return string
     */
    public function render(): string
    {
        return $this->renderer->render($this->manager->getModels());
    }

    /**
     * Collects structured data for the given object.
     *
     * @param mixed $object
     */
    public function collect(mixed $object = null): void
    {
        $this->manager->collect($object);
    }

    /**
     * @param Category[] $crumbs
     */
    public function collectBreadcrumbs(array $crumbs): void
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
        $this->manager->collect(new BreadcrumbContext($items));
    }
}
