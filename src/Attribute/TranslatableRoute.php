<?php

namespace Greendot\EshopBundle\Attribute;

/**
 * SINGLE PARAM USAGE:
 *
 * ```php
 * #[TranslatableRoute(class: Category::class, property: 'slug')]
 * #[Route('/{slug}', name: 'category_list')]
 * public function index(Category $category): Response
 * ```
 *
 * MULTIPLE PARAMS USAGE:
 *
 * ```php
 * #[TranslatableRoute(params: [
 * ['param' => 'slug', 'class' => Category::class, 'property' => 'slug'],
 * ['param' => 'position', 'class' => Label::class, 'property' => 'slug'],
 * ])]
 * #[Route('/{slug}/{position}', name: 'position_list_by_label')]
 * public function index(Category $category, ?Label $position = null): Response
 * ```
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class TranslatableRoute
{
    public function __construct(
        public ?string $class = null,
        public string  $property = 'slug',
        public ?array  $params = null,
        public array   $options = [],
    ) {}
}