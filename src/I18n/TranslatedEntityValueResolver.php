<?php

namespace Greendot\EshopBundle\I18n;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Greendot\EshopBundle\Repository\HintedRepositoryBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('controller.argument_value_resolver', ['priority' => 150])]
readonly class TranslatedEntityValueResolver implements ValueResolverInterface
{
    public function __construct(
        private ManagerRegistry $registry,
    ) {}

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $className = $argument->getType();

        if (!$className || !class_exists($className)) {
            return [];
        }

        $paramName = $argument->getName();
        $identifier = $request->attributes->get($paramName);
        if (!$identifier && $paramName !== 'slug') {
            $identifier = $request->attributes->get('slug');
        }

        if (!$identifier || !is_string($identifier)) {
            return [];
        }

        $manager = $this->registry->getManagerForClass($className);
        if (!$manager) {
            return [];
        }

        $repository = $manager->getRepository($className);
        if (!is_subclass_of($repository, HintedRepositoryBase::class)) {
            return [];
        }

        $entity = $repository->findOneByHinted(['slug' => $identifier]);
        if (!$entity) {
            if (!$argument->isNullable()) {
                throw new NotFoundHttpException(sprintf('"%s" object not found by slug "%s" (param "%s").', $className, $identifier, $paramName));
            }
            return [null];
        }

        return [$entity];
    }
}