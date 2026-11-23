<?php

namespace Greendot\EshopBundle\Tests\Service;

use Greendot\EshopBundle\GreendotEshopBundle;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
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

    public function testPayBySquareBeneficiaryNameDefaultsToEmpty(): void
    {
        self::assertSame('', $this->loadPayBySquareConfig([])->getParameter('greendot_eshop.payment.pay_by_square.beneficiary_name'));
    }

    public function testPayBySquareBeneficiaryNameAllowsSeventyCharacters(): void
    {
        $name = str_repeat('š', 70);

        self::assertSame($name, $this->loadPayBySquareConfig(['beneficiary_name' => $name])->getParameter('greendot_eshop.payment.pay_by_square.beneficiary_name'));
    }

    public function testPayBySquareBeneficiaryNameRejectsMoreThanSeventyCharacters(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->loadPayBySquareConfig(['beneficiary_name' => str_repeat('a', 71)]);
    }

    /** @param array<string, string> $config */
    private function loadPayBySquareConfig(array $config): ContainerBuilder
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('kernel.environment', 'test');
        $builder->setParameter('kernel.build_dir', sys_get_temp_dir());
        (new GreendotEshopBundle())->getContainerExtension()->load([['payment' => ['pay_by_square' => $config]]], $builder);

        return $builder;
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
