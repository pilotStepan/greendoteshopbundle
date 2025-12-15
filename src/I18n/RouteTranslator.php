<?php

namespace Greendot\EshopBundle\I18n;

use Greendot\EshopBundle\Attribute\TranslatableRoute;
use JetBrains\PhpStorm\ArrayShape;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Greendot\EshopBundle\Repository\HintedRepositoryBase;

readonly class RouteTranslator
{
    private array $availableLocales;

    public function __construct(
        private RouterInterface        $router,
        private EntityManagerInterface $em,
        private RequestStack           $requestStack,
        ParameterBagInterface          $parameterBag
    )
    {
        $this->availableLocales = $parameterBag->get('app.available.locales') ?? [];
    }

    /**
     * Finds controller used to make request.
     * Checks if it has TranslatableRoute attribute and if it has, returns translated uri based on current request value.
     *
     * @param Request $request
     * @param string $desiredLocale
     * @return string|null
     */
    public function getByRequest(Request $request, string $desiredLocale = 'en'): ?string
    {
        if (!in_array($desiredLocale, $this->availableLocales)) return null;

        $controller = $request->attributes->get('_controller') ?? null;
        if (!$controller) return null;

        $attributes = $this->parseAttribute($controller);
        if (!$attributes or !isset($attributes['class']) or !isset($attributes['property'])) return $this->buildUri($request, $desiredLocale);
        $class = $attributes['class'];
        $property = $attributes['property'];

        $currentPropertyValue = $this->getArgumentFromRequest($property, $request); //aka slug
        if (!$currentPropertyValue) return null;

        $translatedUri = $this->resolveTranslatableAttributeForClass($class, $currentPropertyValue, $property, $desiredLocale);
        return $this->buildUri($request, $desiredLocale, [$property => $translatedUri]);
    }

    /**
     * Tries to find translation for request in request stack (use in case of nested requests e.g: function called from within template rendered via different controller then the main request)
     *
     * @param string $desiredLocale
     * @return string|null
     */
    public function getByRequestStack(string $desiredLocale = 'en'): ?string
    {
        if (!in_array($desiredLocale, $this->availableLocales)) return null;

        $requests = [$this->requestStack->getMainRequest(), $this->requestStack->getCurrentRequest(), $this->requestStack->getParentRequest()];
        foreach ($requests as $request) {
            $result = $this->getByRequest($request, $desiredLocale);
            if ($result) return $result;
        }
        return null;
    }

    /**
     * returns class and property in array, taken from the TranslatableRoute attribute on route
     *
     * @param string $controllerClass
     * @return array|null
     */
    #[ArrayShape(['class' => 'string', 'property' => 'string'])]
    private function parseAttribute(string $controllerClass): ?array
    {
        [$controllerClass, $method] = explode('::', $controllerClass);
        try {
            $reflectionMethod = new \ReflectionMethod($controllerClass, $method);
        } catch (\ReflectionException $e) {
            return null;
        }

        $attributes = $reflectionMethod->getAttributes(TranslatableRoute::class);
        if (count($attributes) !== 1) return null;

        $attribute = $attributes[0];
        if (!$attribute instanceof \ReflectionAttribute or $attribute->getName() !== TranslatableRoute::class) {
            return null;
        }
        return $attribute->getArguments() ?? null;
    }

    private function getArgumentFromRequest(string $key, Request $request): ?string
    {
        return $request->attributes->get($key, null);
    }

    /**
     * Takes in what entity ($class) to search, finds translation value of given property in target locale ($targetLocale) by current property value ($currentPropertyValue) and returns it
     *
     * @param string $class
     * @param string $currentPropertyValue
     * @param string $translatablePropertyName
     * @param string $targetLocale
     * @return string|null
     */
    private function resolveTranslatableAttributeForClass(
        string $class, //entity with translatable attribute used in route
        string $currentPropertyValue, //current translated value used in route (e.g: "/kontakt", "/contants")
        string $translatablePropertyName = 'slug', //property name in entity that is translatable and used in route (most commonly slug)
        string $targetLocale = 'en',
    ): ?string
    {
        $repo = $this->em->getRepository($class);
        if (!$repo) return null;

        if (is_subclass_of($repo, HintedRepositoryBase::class)) {
            $entity = $repo->findOneByHinted([$translatablePropertyName => $currentPropertyValue]);
            if ($entity) {
                return $repo->findPropertyInLocale($entity, $translatablePropertyName, $targetLocale);
            }
        }
        return null;
    }

    /**
     * Builds uri based on current controller and route name
     *
     * @param Request $request
     * @param string  $locale
     * @param array   $modifiedAttributes
     * @return string|null
     */
    private function buildUri(Request $request, string $locale = 'en', array $modifiedAttributes = []): ?string
    {
        $modifiedAttributes['_locale'] = $locale;
        $routeParameters = $request->attributes->get('_route_params') ?? [];
        foreach ($modifiedAttributes as $key => $modifiedAttribute){
            $routeParameters[$key] = $modifiedAttribute;
        }

        $path = $request->attributes?->get('_route' ?? null);
        if (!$path){
            $controller = $request->attributes->get('_controller') ?? null;
            if (!$controller) return null;
            $path = $this->getPathByController($controller);
        }
        if (!$path) return null;

        return $this->router->generate($path, $routeParameters);
    }

    private function getPathByController(string $controller): ?string
    {
        foreach ($this->router->getRouteCollection()->all() as $routeName => $route) {
            if ($route->getDefault('_controller') == $controller) {
                return $route->getDefault('_canonical_route') ?? $routeName;
            }
        }
        return null;
    }

}
