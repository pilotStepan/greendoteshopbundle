<?php

namespace Greendot\EshopBundle\Tests\App;

use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Kernel for HTTP-level functional tests against #[ApiResource] endpoints.
 *
 * Unlike TestKernel (which avoids Doctrine/API Platform entirely), this kernel boots
 * a real Doctrine + API Platform + Security stack backed by MariaDB (TEST_DATABASE_URL), but does NOT
 * register the real GreendotEshopBundle. The bundle's own config/services.yaml
 * autowires everything under src/* (SMS, payment, JWT auth, OAuth2, ...), which would
 * require credentials/bundles not available in tests. Instead, tests/App/config/api/
 * services.yaml wires a curated subset of services needed by the entities under test.
 */
class ApiTestKernel extends Kernel
{
    /** Local default, also set in phpunit.xml; CI sets its own. Start the server with `composer test:db`. */
    public const DEFAULT_DATABASE_URL = 'mysql://root:root@127.0.0.1:33061/eshop_test?serverVersion=10.5.29-MariaDB&charset=utf8mb4';

    public static function databaseUrl(): string
    {
        $url = $_SERVER['TEST_DATABASE_URL'] ?? $_ENV['TEST_DATABASE_URL'] ?? getenv('TEST_DATABASE_URL');
        if (!\is_string($url) || $url === '') {
            throw new \LogicException('Set TEST_DATABASE_URL to a MariaDB database (phpunit.xml does).');
        }

        return $url;
    }

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new SecurityBundle(),
            new DoctrineBundle(),
            new ApiPlatformBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(__DIR__ . '/config/api/framework.yaml');
        $loader->load(__DIR__ . '/config/api/doctrine.yaml');
        $loader->load(__DIR__ . '/config/api/api_platform.yaml');
        $loader->load(__DIR__ . '/config/api/security.yaml');
        $loader->load(__DIR__ . '/config/api/services.yaml');
    }

    public function getCacheDir(): string
    {
        // debug=true gives Symfony's normal filemtime-based cache freshness check, so the
        // compiled container is reused across runs unless source files actually changed -
        // this directory is safe to persist (no random suffix needed, unlike before).
        return $this->getProjectDir() . '/var/cache/api_test/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/var/log';
    }
}
