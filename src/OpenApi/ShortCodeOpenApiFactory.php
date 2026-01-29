<?php

namespace Greendot\EshopBundle\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\OpenApi;

/**
 * Decorates OpenApiFactoryInterface to add X-ShortCode-replace header to all GET requests
 */
class ShortCodeOpenApiFactory implements OpenApiFactoryInterface
{

    public function __construct(
        private readonly OpenApiFactoryInterface $decorated
    ){}

    /**
     * @inheritDoc
     */
    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);
        foreach ($openApi->getPaths()->getPaths() as $path =>$pathItem){
            assert($pathItem instanceof PathItem);
            $operation = $pathItem->getGet();
            if (!$operation) continue;

            $parameter = new Parameter(
                name: 'X-ShortCode-replace',
                in: 'header',
                description: 'Set to "true" to replace all shortcode in all fields',
                required: false,
                schema: [
                    'type' => 'string',
                    'example' => 'true',
                ]
            );

            $openApi->getPaths()->addPath($path, $pathItem->withGet(
                $operation->withParameters(array_merge($operation->getParameters(), [$parameter]))
            ));
        }
        return $openApi;
    }
}