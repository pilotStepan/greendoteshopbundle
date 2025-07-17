<?php

namespace Greendot\EshopBundle\Service\ShortCodes;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class ShortCodeProvider
{
    /** @var ShortCodeInterface[] */
    private iterable $shortCodeServices;

    public function __construct(
        #[AutowireIterator('app.short_code')]
        iterable $shortCodeServices
    )
    {
        $this->shortCodeServices = $shortCodeServices;
    }

    /**
     * @return ShortCodeInterface[]
     */
    public function getSupported(string $class): iterable
    {
        foreach ($this->shortCodeServices as $shortCodeService) {
            if ($shortCodeService->supports($class)) yield $shortCodeService;
        }
    }
}