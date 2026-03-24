<?php

namespace Greendot\EshopBundle\StructuredData\Service;

use Greendot\EshopBundle\StructuredData\Model\AbstractSchemaType;

/**
 * Renders structured data models as JSON-LD script tags.
 */
class StructuredDataRenderer
{
    /**
     * Renders a list of models as JSON-LD script tags.
     *
     * @param AbstractSchemaType[] $models
     * @param bool                 $pretty
     * @return string
     */
    public function render(array $models, bool $pretty = true): string
    {
        if (empty($models)) {
            return '';
        }

        $options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $options |= JSON_PRETTY_PRINT;
        }


        $graph = [
            '@context' => 'https://schema.org',
            '@graph' => [],
        ];

        foreach ($models as $model) {
            $serialized = $model->jsonSerialize();
            $graph['@graph'][] = $serialized;
        }

        return sprintf('<script type="application/ld+json">%s</script>', json_encode($graph, $options));
    }
}
