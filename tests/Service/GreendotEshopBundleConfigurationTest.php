<?php

namespace Greendot\EshopBundle\Tests\Service;

use Greendot\EshopBundle\GreendotEshopBundle;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Finder\Finder;

/**
 * Guards against services declaring #[Autowire(param: 'greendot_eshop.*')]
 * for a parameter that GreendotEshopBundle never actually defines
 */
class GreendotEshopBundleConfigurationTest extends TestCase
{
    private const PSR4_PREFIX = 'Greendot\\EshopBundle\\';

    public function testAllAutowiredBundleParametersAreDefinedByTheBundle(): void
    {
        $referencedParams = $this->collectAutowiredBundleParams();
        self::assertNotEmpty($referencedParams, 'Expected to find at least one #[Autowire(param: "greendot_eshop.*")] usage in src/.');

        $builder = new ContainerBuilder();
        $builder->setParameter('kernel.environment', 'test');
        $builder->setParameter('kernel.build_dir', sys_get_temp_dir());
        (new GreendotEshopBundle())->getContainerExtension()->load([], $builder);

        foreach ($referencedParams as $paramName => $classes) {
            self::assertTrue(
                $builder->hasParameter($paramName),
                sprintf(
                    'Parameter "%s" is referenced via #[Autowire(param: ...)] by %s but is never set in GreendotEshopBundle::loadExtension().',
                    $paramName,
                    implode(', ', $classes)
                )
            );
        }
    }

    /**
     * @return array<string, string[]> parameter name => list of FQCNs referencing it
     */
    private function collectAutowiredBundleParams(): array
    {
        $srcDir = dirname(__DIR__, 2) . '/src';
        $params = [];

        foreach ((new Finder())->files()->in($srcDir)->name('*.php') as $file) {
            $relativePath = substr($file->getPathname(), strlen($srcDir) + 1);
            $fqcn = self::PSR4_PREFIX . str_replace(['/', '\\\\', '.php'], ['\\', '\\', ''], $relativePath);

            if (!class_exists($fqcn) && !interface_exists($fqcn)) {
                continue;
            }

            $reflection = new ReflectionClass($fqcn);
            if ($reflection->isInterface() || $reflection->isAbstract()) {
                continue;
            }

            $constructor = $reflection->getConstructor();
            if ($constructor === null) {
                continue;
            }

            foreach ($constructor->getParameters() as $parameter) {
                foreach ($parameter->getAttributes(Autowire::class) as $attribute) {
                    $paramName = $attribute->getArguments()['param'] ?? null;
                    if (is_string($paramName) && str_starts_with($paramName, 'greendot_eshop.')) {
                        $params[$paramName][] = $fqcn;
                    }
                }
            }
        }

        return $params;
    }
}
