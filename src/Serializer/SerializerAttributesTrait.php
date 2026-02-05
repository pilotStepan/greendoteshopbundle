<?php

namespace Greendot\EshopBundle\Serializer;

trait SerializerAttributesTrait
{
    private const ALREADY_CALLED = 'NORMALIZER_ALREADY_CALLED';

    private function isFieldRequested(string $field, ?array $attributes): bool
    {
        if ($attributes === null) {
            return true;
        }

        return isset($attributes[$field]) || in_array($field, $attributes, true);
    }
}