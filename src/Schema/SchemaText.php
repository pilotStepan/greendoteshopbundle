<?php

namespace Greendot\EshopBundle\Schema;

/**
 * Text values for schema.org markup: plain text only, no HTML tags or entities.
 */
final class SchemaText
{
    /**
     * Strips tags, decodes entities and collapses whitespace; returns null for empty results
     * so the property is left out of the markup.
     */
    public static function plain(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return $text === '' ? null : $text;
    }
}
