<?php

namespace Greendot\EshopBundle\Export\Util;

use Greendot\EshopBundle\Entity\Project\Parameter;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Builds a unique, descriptive offer name (PRODUCTNAME) for comparison-shopping feeds (Zboží, Heureka, ...).
 *
 * Feeds reject offers whose names repeat or lack distinguishing data (size, colour, length, ...).
 * The variant name alone is unreliable: it usually just repeats the product name, and products cloned
 * in the admin keep the variant name of the original product. The name is therefore assembled as
 * "<base> – <attributes>", where attributes are the variant's distinguishing parameter values.
 * If the data still cannot tell two offers apart, the variant's code is appended as a last resort.
 *
 * Returns plain text — callers are responsible for escaping.
 */
class FeedOfferNameBuilder implements ResetInterface
{
    private const ATTRIBUTE_SEPARATOR = ' – ';

    /** @var array<string, int> normalized product name => number of active products with that name */
    private array $productNameCounts = [];

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function build(ProductVariant $variant): string
    {
        $product = $variant->getProduct();
        if (!$product) {
            return $this->normalize($variant->getName());
        }

        // Everything derived from the siblings is computed once per call (products may have many variants)
        $siblings = $this->getActiveSiblings($product, $variant);
        $valueLists = [];
        $variantNameCounts = [];
        foreach ($siblings as $sibling) {
            $valueLists[spl_object_id($sibling)] = $this->getGroupedValueLists($sibling);
            $key = mb_strtolower($this->normalize($sibling->getName()));
            $variantNameCounts[$key] = ($variantNameCounts[$key] ?? 0) + 1;
        }
        $groups = $this->getAttributeGroups($product, $siblings, $valueLists);
        $productName = $this->normalize($product->getName());

        $base = $this->getBaseName($variant, $productName, count($siblings), $variantNameCounts);
        $name = $this->buildDescriptiveName($base, $groups, $valueLists[spl_object_id($variant)]);

        $reason = null;
        foreach ($siblings as $sibling) {
            if ($sibling === $variant) {
                continue;
            }
            $siblingBase = $this->getBaseName($sibling, $productName, count($siblings), $variantNameCounts);
            if ($this->equals($name, $this->buildDescriptiveName($siblingBase, $groups, $valueLists[spl_object_id($sibling)]))) {
                $reason = 'same name as another variant of the product';
                break;
            }
        }
        if (!$reason && $this->equals($base, $productName) && $this->sharesNameWithOtherProduct($productName)) {
            $reason = 'product name shared with another active product';
        }

        if ($reason) {
            $code = $this->normalize($variant->getExternalId()) ?: '#' . $variant->getId();
            $this->logger->warning('Feed offer name is not distinguishable, appending variant code.', [
                'productId' => $product->getId(),
                'variantId' => $variant->getId(),
                'name' => $name,
                'reason' => $reason,
            ]);
            $name .= ' (' . $code . ')';
        }

        return $name;
    }

    public function reset(): void
    {
        $this->productNameCounts = [];
    }

    /**
     * @param string[] $groups attribute group keys
     * @param array<string, array<string, string>> $valueLists the variant's values by group key
     */
    private function buildDescriptiveName(string $base, array $groups, array $valueLists): string
    {
        $attributes = [];
        foreach ($groups as $groupKey) {
            $value = isset($valueLists[$groupKey]) ? implode(', ', $valueLists[$groupKey]) : '';
            if ($value === '' || $this->containsWord($base, $value)) {
                continue;
            }
            $attributes[mb_strtolower($value)] = $value;
        }

        return $attributes ? $base . self::ATTRIBUTE_SEPARATOR . implode(', ', $attributes) : $base;
    }

    /**
     * @param array<string, int> $variantNameCounts lower-cased variant name => number of siblings using it
     */
    private function getBaseName(ProductVariant $variant, string $productName, int $siblingCount, array $variantNameCounts): string
    {
        $variantName = $this->normalize($variant->getName());

        if ($productName === '') {
            return $variantName;
        }
        if ($variantName === '' || $this->equals($variantName, $productName)) {
            return $productName;
        }
        // "Product name - 50 m" style variant names carry the distinguishing info themselves
        if ($this->contains($variantName, $productName)) {
            return $variantName;
        }
        // Variant names that differ from each other within the product are meaningful ("... LF1" / "... LF2")
        if ($siblingCount > 1 && ($variantNameCounts[mb_strtolower($variantName)] ?? 0) === 1) {
            return $variantName;
        }

        // Otherwise the variant name is most likely stale (copied from another product) — trust the product name
        return $productName;
    }

    /**
     * Parameter groups whose values should be part of the name:
     *  - groups flagged as variant-defining on the product;
     *  - any group whose values differ between the product's active variants.
     * Groups holding several values per variant (e.g. a list of usages) are tags describing the product,
     * not variant attributes, and are never used — they would only bloat the name.
     *
     * @param ProductVariant[] $siblings
     * @param array<int, array<string, array<string, string>>> $valueLists sibling object id => values by group key
     * @return string[] group keys
     */
    private function getAttributeGroups(Product $product, array $siblings, array $valueLists): array
    {
        $valuesByGroup = [];
        $multiValueGroups = [];
        foreach ($siblings as $index => $sibling) {
            foreach ($valueLists[spl_object_id($sibling)] as $groupKey => $values) {
                $valuesByGroup[$groupKey][$index] = mb_strtolower(implode(', ', $values));
                if (count($values) > 1) {
                    $multiValueGroups[$groupKey] = true;
                }
            }
        }

        $groups = [];
        foreach ($product->getProductParameterGroups() as $productParameterGroup) {
            $group = $productParameterGroup->getParameterGroup();
            if (!$group || !$productParameterGroup->isIsVariant()) {
                continue;
            }
            $groupKey = $this->groupKey($group->getId(), $group->getName());
            if (!isset($multiValueGroups[$groupKey])) {
                $groups[$groupKey] = true;
            }
        }

        if (count($siblings) > 1) {
            foreach ($valuesByGroup as $groupKey => $values) {
                if (isset($multiValueGroups[$groupKey])) {
                    continue;
                }
                // differs if some sibling lacks the group or the values are not all equal
                if (count($values) < count($siblings) || count(array_unique($values)) > 1) {
                    $groups[$groupKey] = true;
                }
            }
        }

        return array_keys($groups);
    }

    /**
     * @return array<string, array<string, string>> group key => distinct formatted values of the variant
     */
    private function getGroupedValueLists(ProductVariant $variant): array
    {
        $values = [];
        foreach ($variant->getParameters() as $parameter) {
            $group = $parameter->getParameterGroup();
            if (!$group) {
                continue;
            }
            $value = $this->formatParameter($parameter);
            if ($value === '') {
                continue;
            }
            $values[$this->groupKey($group->getId(), $group->getName())][mb_strtolower($value)] = $value;
        }

        return $values;
    }

    private function formatParameter(Parameter $parameter): string
    {
        $group = $parameter->getParameterGroup();
        $data = $this->normalize($parameter->getData());

        if ($group?->getParameterGroupFormat()?->getName() === 'color') {
            return $this->normalize($parameter->getColorName()) ?: $data;
        }

        $unit = $this->normalize($group?->getUnit());
        if ($data === '' || $unit === '' || $this->containsWord($data, $unit)) {
            return $data;
        }

        return $data . ' ' . $unit;
    }

    /**
     * @return ProductVariant[] active variants of the product (always including $variant itself)
     */
    private function getActiveSiblings(Product $product, ProductVariant $variant): array
    {
        $siblings = [];
        foreach ($product->getProductVariants() as $sibling) {
            if ($sibling === $variant || ($sibling->isIsActive() && !$sibling->isIsDeleted())) {
                $siblings[] = $sibling;
            }
        }
        if (!in_array($variant, $siblings, true)) {
            $siblings[] = $variant;
        }

        return $siblings;
    }

    private function sharesNameWithOtherProduct(string $name): bool
    {
        if ($name === '') {
            return false;
        }
        $key = mb_strtolower($name);
        if (!isset($this->productNameCounts[$key])) {
            $this->productNameCounts[$key] = $this->productRepository->countActiveByName($name);
        }

        return $this->productNameCounts[$key] > 1;
    }

    private function groupKey(?int $id, ?string $name): string
    {
        return $id !== null ? 'id:' . $id : 'name:' . mb_strtolower((string) $name);
    }

    private function normalize(?string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags((string) $text)) ?? '');
    }

    private function equals(string $a, string $b): bool
    {
        return mb_strtolower($a) === mb_strtolower($b);
    }

    private function contains(string $haystack, string $needle): bool
    {
        return $needle !== '' && mb_stripos($haystack, $needle) !== false;
    }

    /**
     * Whole-word containment, so that e.g. size "S" is not considered present in "Kalhoty Salvia".
     */
    private function containsWord(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }
        $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($needle, '/') . '(?![\p{L}\p{N}])/iu';

        return (bool) preg_match($pattern, $haystack);
    }
}
