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
     * @param bool $pretty
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

        $output = '';

        foreach ($models as $model) {
            $json = json_encode($model, $options);
            $output .= sprintf('<script type="application/ld+json">%s</script>', $json);
        }

        return $output;
    }

    /**
     * Renders a single model as a JSON-LD script tag.
     *
     * @param AbstractSchemaType $model
     * @param bool $pretty
     * @return string
     */
    public function renderSingle(AbstractSchemaType $model, bool $pretty = true): string
    {
        return $this->render([$model], $pretty);
    }
}
