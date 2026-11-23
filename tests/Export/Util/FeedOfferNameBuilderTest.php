<?php

namespace Greendot\EshopBundle\Tests\Export\Util;

use Doctrine\Common\Collections\ArrayCollection;
use Greendot\EshopBundle\Entity\Project\Parameter;
use Greendot\EshopBundle\Entity\Project\ParameterGroup;
use Greendot\EshopBundle\Entity\Project\ParameterGroupFormat;
use Greendot\EshopBundle\Entity\Project\Product;
use Greendot\EshopBundle\Entity\Project\ProductParameterGroup;
use Greendot\EshopBundle\Entity\Project\ProductVariant;
use Greendot\EshopBundle\Export\Util\FeedOfferNameBuilder;
use Greendot\EshopBundle\Repository\Project\ProductRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class FeedOfferNameBuilderTest extends TestCase
{
    public function testSingleVariantWithProductNameUsesProductName(): void
    {
        $product = $this->product('Vonná svíčka Satya Palo Santo');
        $variant = $this->variant($product, 'Vonná svíčka Satya Palo Santo');

        self::assertSame('Vonná svíčka Satya Palo Santo', $this->builder()->build($variant));
    }

    public function testStaleVariantNameCopiedFromAnotherProductIsIgnored(): void
    {
        $product = $this->product('Inhalační tyčinka proti únavě ');
        $variant = $this->variant($product, 'Inhalační tyčinka proti stresu');

        self::assertSame('Inhalační tyčinka proti únavě', $this->builder()->build($variant));
    }

    public function testStaleNamesSharedByAllVariantsFallBackToProductNameWithSize(): void
    {
        $product = $this->product('Ponožky na jógu Tavi Luanna Pacific');
        $size = $this->group('Velikosti');
        $s = $this->variant($product, 'Ponožky na jógu Tavi Lola Marina', [[$size, 'S (36 - 38.5)']]);
        $m = $this->variant($product, 'Ponožky na jógu Tavi Lola Marina', [[$size, 'M (39 - 42.5)']]);

        $builder = $this->builder();
        self::assertSame('Ponožky na jógu Tavi Luanna Pacific – S (36 - 38.5)', $builder->build($s));
        self::assertSame('Ponožky na jógu Tavi Luanna Pacific – M (39 - 42.5)', $builder->build($m));
    }

    public function testSizeLetterContainedInsideAWordOfTheNameIsStillAppended(): void
    {
        $product = $this->product('Kalhoty Purusha Azul Piedra');
        $size = $this->group('Velikosti');
        $s = $this->variant($product, 'Kalhoty Purusha Azul Piedra', [[$size, 'S']]);
        $l = $this->variant($product, 'Kalhoty Purusha Azul Piedra', [[$size, 'L']]);

        $builder = $this->builder();
        self::assertSame('Kalhoty Purusha Azul Piedra – S', $builder->build($s));
        self::assertSame('Kalhoty Purusha Azul Piedra – L', $builder->build($l));
    }

    public function testValueAlreadyPresentAsWordInNameIsNotRepeated(): void
    {
        $product = $this->product('Jógová podložka Natura 4 mm');
        $thickness = $this->group('Tloušťka', 'mm');
        $colour = $this->group('Barva');
        $a = $this->variant($product, 'Jógová podložka Natura 4 mm', [[$thickness, '4'], [$colour, 'modrá']]);
        $b = $this->variant($product, 'Jógová podložka Natura 4 mm', [[$thickness, '4'], [$colour, 'zelená']]);

        self::assertSame('Jógová podložka Natura 4 mm – modrá', $this->builder()->build($a));
        self::assertSame('Jógová podložka Natura 4 mm – zelená', $this->builder()->build($b));
    }

    public function testVariantNameExtendingProductNameIsKept(): void
    {
        $product = $this->product('Provázek k žaluzii Climax 1,4 mm');
        $length = $this->group('Délka', 'm');
        $a = $this->variant($product, 'Provázek k žaluzii Climax 1,4 mm - 13 m', [[$length, '13']]);
        $b = $this->variant($product, 'Provázek k žaluzii Climax 1,4 mm - 4 m', [[$length, '4']]);

        $builder = $this->builder();
        self::assertSame('Provázek k žaluzii Climax 1,4 mm - 13 m', $builder->build($a));
        self::assertSame('Provázek k žaluzii Climax 1,4 mm - 4 m', $builder->build($b));
    }

    public function testDistinctVariantNamesAreKept(): void
    {
        $product = $this->product('Aplikátor filament tapes LF1 a LF2');
        $a = $this->variant($product, 'Aplikátor filament tapes LF1');
        $b = $this->variant($product, 'Aplikátor filament tapes LF2');

        $builder = $this->builder();
        self::assertSame('Aplikátor filament tapes LF1', $builder->build($a));
        self::assertSame('Aplikátor filament tapes LF2', $builder->build($b));
    }

    public function testDifferingParametersAreAppendedAndIdenticalVariantsGetCode(): void
    {
        $product = $this->product('Lotos');
        $length = $this->group('Délka', 'cm');
        $type = $this->group('Typ šperku');
        $v1 = $this->variant($product, 'Lotos', [[$length, '42'], [$length, '42'], [$type, 'náhrdelníky']], '120050031');
        $v2 = $this->variant($product, 'Lotos', [[$length, '42'], [$length, '42'], [$type, 'náhrdelníky']], '120050021');
        $v3 = $this->variant($product, 'Lotos', [[$length, '45'], [$type, 'náhrdelníky']], '120050032');

        $builder = $this->builder();
        self::assertSame('Lotos – 42 cm (120050031)', $builder->build($v1));
        self::assertSame('Lotos – 42 cm (120050021)', $builder->build($v2));
        self::assertSame('Lotos – 45 cm', $builder->build($v3));
    }

    public function testInactiveVariantsAreIgnored(): void
    {
        $product = $this->product('Lotos');
        $length = $this->group('Délka', 'cm');
        $active = $this->variant($product, 'Lotos', [[$length, '42']]);
        $this->variant($product, 'Lotos', [[$length, '50']])->setIsActive(false);

        self::assertSame('Lotos', $this->builder()->build($active));
    }

    public function testVariantFlaggedParameterGroupIsAppendedAndColourUsesName(): void
    {
        $product = $this->product('Jógová podložka');
        $colour = $this->group('Barva', null, 'color');
        $ppg = (new ProductParameterGroup())->setParameterGroup($colour)->setIsVariant(true);
        $product->setProductParameterGroups(new ArrayCollection([$ppg]));
        $variant = $this->variant($product, 'Jógová podložka', [[$colour, '#0000ff', 'modrá']]);

        self::assertSame('Jógová podložka – modrá', $this->builder()->build($variant));
    }

    public function testFlaggedMultiValueGroupIsAppendedOnlyWhenItDiffers(): void
    {
        $product = $this->product('Suchý zip Velcro');
        $width = $this->group('Šířka', 'mm');
        $usage = $this->group('Použití');
        $product->setProductParameterGroups(new ArrayCollection([
            (new ProductParameterGroup())->setParameterGroup($width)->setIsVariant(true),
            (new ProductParameterGroup())->setParameterGroup($usage)->setIsVariant(true),
        ]));
        $a = $this->variant($product, 'Suchý zip Velcro', [[$width, '20'], [$usage, 'na sklo'], [$usage, 'na plast']]);
        $b = $this->variant($product, 'Suchý zip Velcro', [[$width, '50'], [$usage, 'na sklo'], [$usage, 'na plast']]);

        self::assertSame('Suchý zip Velcro – 20 mm', $this->builder()->build($a));
        self::assertSame('Suchý zip Velcro – 50 mm', $this->builder()->build($b));
    }

    public function testDifferingMultiValueGroupIsNotUsed(): void
    {
        $product = $this->product('Suchý zip Velcro');
        $width = $this->group('Šířka', 'mm');
        $usage = $this->group('Použití');
        $a = $this->variant($product, 'Suchý zip Velcro', [[$width, '20'], [$usage, 'na sklo'], [$usage, 'na plast']]);
        $b = $this->variant($product, 'Suchý zip Velcro', [[$width, '50'], [$usage, 'na sklo'], [$usage, 'na kov']]);

        self::assertSame('Suchý zip Velcro – 20 mm', $this->builder()->build($a));
        self::assertSame('Suchý zip Velcro – 50 mm', $this->builder()->build($b));
    }

    public function testFlaggedSingleValueGroupIsAppendedEvenForSingleVariant(): void
    {
        $product = $this->product('Páska oboustranná');
        $width = $this->group('Šířka', 'mm');
        $product->setProductParameterGroups(new ArrayCollection([
            (new ProductParameterGroup())->setParameterGroup($width)->setIsVariant(true),
        ]));
        $variant = $this->variant($product, 'Páska oboustranná', [[$width, '19']]);

        self::assertSame('Páska oboustranná – 19 mm', $this->builder()->build($variant));
    }

    public function testProductNameSharedWithAnotherProductGetsCode(): void
    {
        $product = $this->product('Mala růženín');
        $variant = $this->variant($product, 'Mala růženín', [], '510160000');

        self::assertSame('Mala růženín (510160000)', $this->builder(2)->build($variant));
    }

    private function builder(int $productsWithSameName = 1): FeedOfferNameBuilder
    {
        $repository = $this->createMock(ProductRepository::class);
        $repository->method('countActiveByName')->willReturn($productsWithSameName);

        return new FeedOfferNameBuilder($repository, new NullLogger());
    }

    private function product(string $name): Product
    {
        return (new Product())->setName($name)->setIsActive(true);
    }

    /**
     * @param array<array{0: ParameterGroup, 1: string, 2?: string}> $parameters [group, data, colourName]
     */
    private function variant(Product $product, string $name, array $parameters = [], ?string $externalId = null): ProductVariant
    {
        $variant = (new ProductVariant())->setName($name)->setIsActive(true)->setExternalId($externalId);
        foreach ($parameters as $definition) {
            $parameter = (new Parameter())->setParameterGroup($definition[0])->setData($definition[1]);
            $parameter->setColorName($definition[2] ?? null);
            $variant->addParameter($parameter);
        }
        $product->addProductVariant($variant);

        return $variant;
    }

    private function group(string $name, ?string $unit = null, ?string $format = null): ParameterGroup
    {
        $group = (new ParameterGroup())->setName($name)->setUnit($unit);
        if ($format) {
            $group->setParameterGroupFormat((new ParameterGroupFormat())->setName($format));
        }

        return $group;
    }
}
