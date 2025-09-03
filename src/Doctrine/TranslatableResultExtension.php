<?php

namespace Greendot\EshopBundle\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryResultItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Gedmo\Translatable\Query\TreeWalker\TranslationWalker;
use Gedmo\Translatable\Translatable;
use Gedmo\Translatable\TranslatableListener;
use ProxyManager\FileLocator\FileLocatorInterface;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Yaml\Yaml;

class TranslatableResultExtension implements QueryItemExtensionInterface, QueryCollectionExtensionInterface
{


    private RequestStack $requestStack;
    private ParameterBagInterface $parameterBag;

    public function __construct(RequestStack $requestStack, ParameterBagInterface $parameterBag)
    {
        $this->requestStack = $requestStack;
        $this->parameterBag = $parameterBag;
        $this->defaultLocale = 'cs';
        $this->locale = $this->defaultLocale;
    }

    /**
     * @throws ReflectionException
     */
    public function supports(string $resourceClass): bool
    {

        $reflection = new ReflectionClass($resourceClass);
        return $reflection->implementsInterface(Translatable::class);
    }

    /**
     * @throws ReflectionException
     */
    public function applyToCollection(
        QueryBuilder                $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string                      $resourceClass,
        Operation                   $operation = null,
        array                       $context = []
    ): void
    {
        /*
         * Commented - was requesting all products query prior to search itself.
         */
        $this->addHints($queryBuilder, $resourceClass);
    }

    /**
     * @throws ReflectionException
     */
    public function applyToItem(
        QueryBuilder                $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string                      $resourceClass,
        array                       $identifiers,
        Operation                   $operation = null,
        array                       $context = []
    ): void
    {
        $this->addHintsToSingle($queryBuilder, $resourceClass);
    }

    /**
     * @throws ReflectionException
     */
    public function addHints(QueryBuilder $queryBuilder, string $resourceClass)
    {
        dump($this->requestStack->getCurrentRequest()->headers->get('referer'));
        if ($this->requestStack->getCurrentRequest()->headers->get('referer') !== null) {
            if (count(array_slice(explode("/", $this->requestStack->getCurrentRequest()->headers->get('referer')), 3, 1)) < 1) {
                $locale = $this->defaultLocale;
            } else {
                $locale = array_slice(explode("/", $this->requestStack->getCurrentRequest()->headers->get('referer')), 3, 1)[0];
            }
        } else {
            $locale = $this->defaultLocale;
        }
        if (!in_array($locale, $this->parameterBag->get('app.available.locales'))) {
            $locale = $this->defaultLocale;
        }
        $this->locale = $locale;
        $this->requestStack->getCurrentRequest()->setLocale($locale);

        if (!$this->supports($resourceClass)) {
            return;
        }


        $query = $queryBuilder->getQuery()->useQueryCache(false);
        $query->setHint(
            Query::HINT_CUSTOM_OUTPUT_WALKER,
            TranslationWalker::class
        );
        $query->setHint(
            TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale // take locale from session or request etc.
        );

        $query->setHint(
            TranslatableListener::HINT_FALLBACK,
            1 // fallback to default values in case if record is not translated
        );
        $query->getResult();
    }


    /**
     * @throws ReflectionException
     */
    public function addHintsToSingle(QueryBuilder $queryBuilder, string $resourceClass)
    {
        if ($this->requestStack->getCurrentRequest()->headers->get('referer') !== null) {
            if (count(array_slice(explode("/", $this->requestStack->getCurrentRequest()->headers->get('referer')), 3, 1)) < 1) {
                $locale = $this->defaultLocale;
            } else {
                $locale = array_slice(explode("/", $this->requestStack->getCurrentRequest()->headers->get('referer')), 3, 1)[0];
            }
        } else {
            $locale = $this->defaultLocale;
        }
        if (!in_array($locale,  $this->parameterBag->get('app.available.locales'))) {
            $locale = $this->defaultLocale;
        }
        $this->locale = $locale;
        $this->requestStack->getCurrentRequest()->setLocale($locale);

        if (!$this->supports($resourceClass)) {
            return;
        }


        $query = $queryBuilder->getQuery()->useQueryCache(false);
        $query->setHint(
            Query::HINT_CUSTOM_OUTPUT_WALKER,
            TranslationWalker::class
        );
        $query->setHint(
            TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale // take locale from session or request etc.
        );

        $query->setHint(
            TranslatableListener::HINT_FALLBACK,
            1 // fallback to default values in case if record is not translated
        );
        $query->getResult();

    }

    /**
     * @throws ReflectionException
     */
    public function supportsResult(string $resourceClass, Operation $operation = null, array $context = []): bool
    {
        return $this->supports($resourceClass);
    }

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException|ReflectionException
     */
    public function getResult(
        string       $locale,
        QueryBuilder $queryBuilder,
        string       $resourceClass = null,
        Operation    $operation = null,
        array        $context = []
    ): ?object
    {
        $query = $queryBuilder->getQuery()->useQueryCache(false);
        $query->setHint(
            Query::HINT_CUSTOM_OUTPUT_WALKER,
            TranslationWalker::class
        );
        $query->setHint(
            TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale // take locale from session or request etc.
        );

        $query->setHint(
            TranslatableListener::HINT_FALLBACK,
            1 // fallback to default values in case if record is not translated
        );

        return $query->getSingleResult();
    }
}
