<?php

namespace Greendot\EshopBundle\Tests\App;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Minimal Symfony kernel for functional/endpoint tests within the bundle.
 *
 * It loads only FrameworkBundle and registers just the controllers needed for
 * each test, avoiding the full bundle's DI wiring (API Platform decorations,
 * Doctrine, etc.) which belong to the consuming application.
 *
 * To add more controllers to test, register them in tests/App/config/services.yaml.
 */
class TestKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(__DIR__ . '/config/framework.yaml');
        $loader->load(__DIR__ . '/config/services.yaml');
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/var/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/var/log';
    }
}
