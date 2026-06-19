<?php

namespace Greendot\EshopBundle\Tests\App\Doctrine;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\JsonType;

/**
 * Some bundle entities (Product::$additionalData, Purchase::$additionalInfo, ...) declare
 * #[ORM\Column(nullable: true)] on an untyped `?array` property with no explicit `type:`.
 * Doctrine's attribute driver infers the legacy 'array' column type from that, but DBAL 4
 * removed the 'array' type entirely. This test-only shim re-registers it as a JSON column
 * so the schema can be created; it does not change production mapping.
 */
class LegacyArrayType extends JsonType
{
    public function getName(): string
    {
        return 'array';
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }
}
