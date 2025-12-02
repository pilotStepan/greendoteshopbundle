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

        $slug = $request->attributes->get('slug');
        if (!$slug) {
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

        $entity = $repository->findOneByHinted(['slug' => $slug]);
        if (!$entity) {
            if (!$argument->isNullable()) {
                throw new NotFoundHttpException(sprintf('"%s" object not found by slug "%s".', $className, $slug));
            }
            return [null];
        }

        return [$entity];
    }
}