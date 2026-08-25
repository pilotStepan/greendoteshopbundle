<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Parameter;

class SizeParameterValueSequencer
{
    private const SIZE_PATTERN = '/^(X*)([SL])$/i';

    /**
     * @param Parameter[] $parameters
     * @return Parameter[] same array, values reordered within each size parameterGroup
     */
    public function sort(array $parameters): array
    {
        $indicesByGroup = [];
        foreach ($parameters as $index => $parameter) {
            if (!$this->supports($parameter)) {
                continue;
            }
            $groupId = $parameter->getParameterGroup()?->getId();
            $indicesByGroup[$groupId][] = $index;
        }

        foreach ($indicesByGroup as $indices) {
            $slice = array_map(static fn(int $i) => $parameters[$i], $indices);
            usort($slice, fn(Parameter $a, Parameter $b) => $this->position($a->getData()) <=> $this->position($b->getData()));
            foreach ($indices as $offset => $i) {
                $parameters[$i] = $slice[$offset];
            }
        }

        return $parameters;
    }

    /**
     * @return array{0: int|float, 1: int} [sizeRank, hasSuffix] — compared as a tuple so a
     *         suffixed variant (e.g. "S (36 – 38,5)") always sorts right after its bare base
     *         size ("S") and before the next base size, without perturbing the base ranks.
     */
    private function position(?string $value): array
    {
        $value = trim((string)$value);
        if ($value === '') {
            return [PHP_INT_MAX, 0];
        }

        [$base, $hasSuffix] = $this->splitParentheticalSuffix($value);

        if (str_contains($base, '/')) {
            $ranks = array_map(fn(string $side) => $this->sizeRank(trim($side)), explode('/', $base));
            if (in_array(null, $ranks, true)) {
                return [PHP_INT_MAX, 0];
            }

            return [array_sum($ranks) / count($ranks), $hasSuffix ? 1 : 0];
        }

        $rank = $this->sizeRank($base);
        if ($rank === null) {
            return [PHP_INT_MAX, 0];
        }

        return [$rank, $hasSuffix ? 1 : 0];
    }

    /**
     * Splits a value like "L (43 – 45)" into its base size "L" and a flag that a
     * parenthesised range/unit suffix was present. Values with no such suffix (including
     * plain "L" and combos like "M/L") are returned unchanged.
     *
     * @return array{0: string, 1: bool}
     */
    private function splitParentheticalSuffix(string $value): array
    {
        if (preg_match('/^(.+?)\s*\(.*\)\s*$/', $value, $matches)) {
            return [trim($matches[1]), true];
        }

        return [$value, false];
    }

    private function sizeRank(string $value): ?int
    {
        if (strcasecmp($value, 'M') === 0) {
            return 0;
        }

        if (!preg_match(self::SIZE_PATTERN, $value, $matches)) {
            return null;
        }

        $extraXCount = strlen($matches[1]);
        $side = strtoupper($matches[2]) === 'S' ? -1 : 1;

        return $side * ($extraXCount + 1);
    }

    protected function supports(Parameter $parameter): bool
    {
        return $parameter->getParameterGroup()?->getId() === 2;
    }
}
