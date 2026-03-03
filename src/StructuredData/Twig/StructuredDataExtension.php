<?php

namespace Greendot\EshopBundle\StructuredData\Twig;

use Greendot\EshopBundle\StructuredData\Service\StructuredDataManager;
use Greendot\EshopBundle\StructuredData\Service\StructuredDataRenderer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension for rendering structured data.
 */
class StructuredDataExtension extends AbstractExtension
{
    private StructuredDataManager $manager;
    private StructuredDataRenderer $renderer;

    public function __construct(StructuredDataManager $manager, StructuredDataRenderer $renderer)
    {
        $this->manager = $manager;
        $this->renderer = $renderer;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_structured_data', $this->render(...), ['is_safe' => ['html']]),
            new TwigFunction('collect_structured_data', $this->collect(...)),
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
     * @param object|null $object
     */
    public function collect(?object $object = null): void
    {
        $this->manager->collect($object);
    }
}
