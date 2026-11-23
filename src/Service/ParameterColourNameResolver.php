<?php

namespace Greendot\EshopBundle\Service;

use Greendot\EshopBundle\Entity\Project\Parameter;
use Greendot\EshopBundle\Repository\Project\ColourRepository;

/**
 * Batched equivalent of ParameterEventListener::postLoad: resolves the colour name of
 * every colour parameter with a single query instead of one query per parameter.
 *
 * Callers that hydrate many parameters should disable ParameterEventListener through
 * ListenerManager while loading, then pass the parameters here.
 */
class ParameterColourNameResolver
{
    public function __construct(
        private readonly ColourRepository $colourRepository,
    ) {}

    /**
     * @param iterable<Parameter> $parameters
     */
    public function resolve(iterable $parameters): void
    {
        /** @var array<string, Parameter[]> $parametersByHex */
        $parametersByHex = [];
        foreach ($parameters as $parameter) {
            if ($parameter->getColorName() !== null
                || $parameter->getParameterGroup()?->getParameterGroupFormat()?->getName() !== 'color'
            ) {
                continue;
            }

            $data = (string)$parameter->getData();
            $hex = str_starts_with($data, '#') ? $data : '#' . $data;
            // MySQL compares hex case-insensitively, mirror that for the PHP lookup
            $parametersByHex[strtolower($hex)][] = $parameter;
        }

        if (!$parametersByHex) {
            return;
        }

        foreach ($this->colourRepository->findBy(['hex' => array_keys($parametersByHex)]) as $colour) {
            foreach ($parametersByHex[strtolower((string)$colour->getHex())] ?? [] as $parameter) {
                if ($parameter->getColorName() === null) {
                    $parameter->setColorName($colour->getName());
                }
            }
        }
    }
}
