<?php

namespace Greendot\EshopBundle\Utils;

use Greendot\EshopBundle\Attribute\OmitLocaleAwareListener;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Request;

final class StatelessRequestMatcher
{
    public static function isStateless(?Request $request): bool
    {
        if ($request === null) {
            return false;
        }

        if ($request->attributes->get('_stateless', false)) {
            return true;
        }

        $controller = $request->attributes->get('_controller');

        if (is_string($controller) && str_contains($controller, '\\')) {
            try {
                $ref = new ReflectionMethod($controller);
                return !empty($ref->getAttributes(OmitLocaleAwareListener::class));
            } catch (ReflectionException) {
                // silent fail
            }
        }

        return false;
    }
}
