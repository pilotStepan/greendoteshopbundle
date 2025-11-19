<?php

namespace Greendot\EshopBundle\I18n;

use ReflectionMethod;
use ReflectionException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\RouterInterface;
use Greendot\EshopBundle\Repository\HintedRepositoryBase;
use Symfony\Component\Routing\Exception\ExceptionInterface;

readonly class RouteTranslator
{
    public function __construct(private RouterInterface $router, private EntityManagerInterface $em) {}

    public function getTranslatedUrl(string $referer, string $targetLocale): string
    {
        $path = parse_url($referer, PHP_URL_PATH) ?? '/';

        try {
            $parameters = $this->router->match($path);
        } catch (ExceptionInterface) {
            return $this->router->generate('web_homepage', ['_locale' => $targetLocale]);
        }

        $routeName = $parameters['_route'];
        $sourceLocale = $parameters['_locale'] ?? 'cs';

        $routeParams = array_filter($parameters, fn($key) => !str_starts_with($key, '_'), ARRAY_FILTER_USE_KEY);

        if (isset($routeParams['slug'])) {
            $translatedSlug = $this->resolveTranslatedSlug($parameters, $routeParams['slug'], $sourceLocale, $targetLocale);
            if ($translatedSlug) {
                $routeParams['slug'] = $translatedSlug;
            }
        }

        $routeParams['_locale'] = $targetLocale;

        return $this->router->generate($routeName, $routeParams);
    }

    private function resolveTranslatedSlug(array $matchParams, string $currentSlug, string $sourceLocale, string $targetLocale): ?string
    {
        $controller = $matchParams['_controller'] ?? null;
        if (!$controller) {
            return null;
        }

        try {
            if (str_contains($controller, '::')) {
                [$class, $method] = explode('::', $controller);
                $refMethod = new ReflectionMethod($class, $method);
            } else {
                $refMethod = new ReflectionMethod($controller, '__invoke');
            }
        } catch (ReflectionException $e) {
            return null;
        }

        foreach ($refMethod->getParameters() as $param) {
            $type = $param->getType();
            if (!$type || $type->isBuiltin()) {
                continue;
            }

            $className = $type->getName();

            if (!$this->em->getMetadataFactory()->isTransient($className)) {
                $repo = $this->em->getRepository($className);

                if (is_subclass_of($repo, HintedRepositoryBase::class)) {
                    $entity = $repo->findOneByHinted(['slug' => $currentSlug]);

                    if ($entity) {
                        return $repo->findSlugInLocale($entity, $targetLocale);
                    }
                }
            }
        }

        return null;
    }
}