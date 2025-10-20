<?php

namespace Greendot\EshopBundle\Service;

use Symfony\Component\HttpFoundation\Request;
use ReflectionMethod;
use Greendot\EshopBundle\Attribute\CustomApiEndpoint;

class ApiRequestDetector
{
    public function __construct()
    {
    }

    /**
     * Returns true if this request is either an API Platform request
     * or a custom API endpoint marked with #[CustomApiEndpoint]
     */
    public function isApiRequest(Request $request): bool
    {
        return $this->isApiPlatformRequest($request) || $this->isCustomApiRequest($request);
    }

    /**
     * Detects custom API endpoints using your attribute
     */
    public function isCustomApiRequest(Request $request): bool
    {
        $controller = $request->attributes->get('_controller');
        if (!$controller || !str_contains($controller, '::')) {
            return false;
        }

        [$class, $method] = explode('::', $controller);

        if (!class_exists($class) || !method_exists($class, $method)) {
            return false;
        }

        $reflection = new ReflectionMethod($class, $method);
        return count($reflection->getAttributes(CustomApiEndpoint::class)) > 0;
    }

    /**
     * Detects API Platform requests using the built-in _api_resource_class attribute
     */
    public function isApiPlatformRequest(Request $request): bool
    {
        return $request->attributes->has('_api_resource_class');
    }
}
