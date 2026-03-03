# Structured Data Bundle Implementation

see https://greendot.cz/utils/structured-data.html

This component provides a project-agnostic solution for aggregating and rendering structured data (JSON-LD) on web pages.

## Core Concepts

1.  **Models**: DTOs representing Schema.org types (`Product`, `Offer`, `BreadcrumbList`, etc.).
2.  **Providers**: Classes implementing `StructuredDataProviderInterface` that map domain entities to models.
3.  **Manager**: Service that collects data from all supporting providers for a given context.
4.  **Renderer**: Service that converts models into `<script type="application/ld+json">` tags.

## Usage

### In Twig Templates

1.  **Collect Data**: Call `collect_structured_data(object)` in your template to gather data for a specific entity.
2.  **Render Data**: Call `render_structured_data()` (usually in your base layout's `<head>`) to output the JSON-LD blocks.

```twig
{# product/detail.html.twig #}
{% do collect_structured_data(product) %}

{# layout.html.twig #}
<head>
    {{ render_structured_data() }}
</head>
```

### Creating Custom Providers

Implement `StructuredDataProviderInterface` and register it as a service. It will be automatically tagged and used by the `StructuredDataManager`.

```php
class MyCustomProvider implements StructuredDataProviderInterface
{
    public function supports(?object $object): bool {
        return $object instanceof MyEntity;
    }

    public function provide(?object $object): ?AbstractSchemaType {
        return (new Product())->setName($object->getName());
    }

    public function getPriority(): int {
        return 10; // Higher than default (0)
    }
}
```

## Performance & Optimization

Heavy aggregation of structured data can impact page load times and memory usage.

### Recommended Improvements

1.  **Caching**:
    - Wrap the `StructuredDataManager::collect()` or `StructuredDataRenderer::render()` calls in a cache layer (e.g., Symfony Cache).
    - Structured data is mostly static for a given entity and locale, making it an ideal candidate for caching.

2.  **Deferred/Lazy Loading**:
    - For non-critical SEO data, consider rendering the JSON-LD via an AJAX call or at the bottom of the page (though `<head>` is preferred by search engines).
    - Use `Proxy` objects for heavy relations within providers to avoid unnecessary database hits if the provider ends up not being used.

3.  **Data Limiting**:
    - Avoid listing hundreds of items in `ItemList` or `BreadcrumbList`. The default bundle providers already implement limits (e.g., top 20 products in a category).

4.  **Selective Collection**:
    - Only collect structured data that is actually needed for the specific page type. Avoid global "collect everything" patterns.
