<?php

namespace Greendot\EshopBundle\Tests\Service;

use ReflectionProperty;
use PHPUnit\Framework\TestCase;
use Greendot\EshopBundle\Entity\Project\Parameter;
use Greendot\EshopBundle\Entity\Project\ParameterGroup;
use Greendot\EshopBundle\Service\SizeParameterValueSequencer;

class SizeParameterValueSequencerTest extends TestCase
{
    /** Bundle default: {@see SizeParameterValueSequencer::supports()} recognises group id 2. */
    private const SIZE_GROUP_ID = 2;

    private SizeParameterValueSequencer $sequencer;

    protected function setUp(): void
    {
        $this->sequencer = new SizeParameterValueSequencer();
    }

    public function testSortsSizesIntoLogicalOrderRegardlessOfInputOrder(): void
    {
        $group = $this->group(self::SIZE_GROUP_ID);
        $parameters = $this->parameters($group, ['M', 'XS', 'L', 'S', 'XL', 'M/L', 'XS/S']);

        $sorted = $this->sequencer->sort($parameters);

        $this->assertSame(
            ['XS', 'XS/S', 'S', 'M', 'M/L', 'L', 'XL'],
            array_map(static fn(Parameter $p) => $p->getData(), $sorted),
        );
    }

    public function testSortsSizesNeverExplicitlyListedLikeXxsAndXxxl(): void
    {
        $group = $this->group(self::SIZE_GROUP_ID);
        $parameters = $this->parameters($group, ['XXXL', 'M', 'XXS', 'L', 'XS', 'S', 'XL']);

        $sorted = $this->sequencer->sort($parameters);

        $this->assertSame(
            ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXXL'],
            array_map(static fn(Parameter $p) => $p->getData(), $sorted),
        );
    }

    public function testIsCaseInsensitive(): void
    {
        $group = $this->group(self::SIZE_GROUP_ID);
        $parameters = $this->parameters($group, ['xl', 'm', 'xs']);

        $sorted = $this->sequencer->sort($parameters);

        $this->assertSame(['xs', 'm', 'xl'], array_map(static fn(Parameter $p) => $p->getData(), $sorted));
    }

    public function testUnrecognisedValueSortsAfterKnownSizesButKeepsRelativeOrder(): void
    {
        $group = $this->group(self::SIZE_GROUP_ID);
        $parameters = $this->parameters($group, ['ZZZ-custom', 'M', 'AAA-custom', 'XS']);

        $sorted = $this->sequencer->sort($parameters);

        $this->assertSame(
            ['XS', 'M', 'ZZZ-custom', 'AAA-custom'],
            array_map(static fn(Parameter $p) => $p->getData(), $sorted),
        );
    }

    public function testGroupWithNoRecognisedTokensIsLeftInOriginalOrder(): void
    {
        $group = $this->group(self::SIZE_GROUP_ID);
        $parameters = $this->parameters($group, ['Red', 'Blue', 'Green']);

        $sorted = $this->sequencer->sort($parameters);

        $this->assertSame(['Red', 'Blue', 'Green'], array_map(static fn(Parameter $p) => $p->getData(), $sorted));
    }

    public function testOnlyReordersGroupsThatSupportsRecognises(): void
    {
        $sizeGroup = $this->group(self::SIZE_GROUP_ID);
        $otherGroup = $this->group(1);

        $parameters = [
            $this->parameter($otherGroup, 'Red'),
            $this->parameter($sizeGroup, 'M'),
            $this->parameter($otherGroup, 'Blue'),
            $this->parameter($sizeGroup, 'XS'),
        ];

        $sorted = $this->sequencer->sort($parameters);

        $this->assertSame('Red', $sorted[0]->getData());
        $this->assertSame('XS', $sorted[1]->getData());
        $this->assertSame('Blue', $sorted[2]->getData());
        $this->assertSame('M', $sorted[3]->getData());
    }

    public function testGroupNotRecognisedIsNeverReorderedEvenWithSizeLookingValues(): void
    {
        $group = $this->group(999);
        $parameters = $this->parameters($group, ['XL', 'XS', 'M']);

        $sorted = $this->sequencer->sort($parameters);

        $this->assertSame(['XL', 'XS', 'M'], array_map(static fn(Parameter $p) => $p->getData(), $sorted));
    }

    public function testSupportsCanBeOverriddenByAConsumingApp(): void
    {
        $customSequencer = new class extends SizeParameterValueSequencer {
            protected function supports(Parameter $parameter): bool
            {
                return $parameter->getParameterGroup()?->getId() === 42;
            }
        };

        $defaultSizeGroup = $this->group(self::SIZE_GROUP_ID);
        $customSizeGroup = $this->group(42);

        $defaultGroupParams = $this->parameters($defaultSizeGroup, ['XL', 'XS']);
        $customGroupParams = $this->parameters($customSizeGroup, ['XL', 'XS']);

        $this->assertSame(['XL', 'XS'], array_map(static fn(Parameter $p) => $p->getData(), $customSequencer->sort($defaultGroupParams)));
        $this->assertSame(['XS', 'XL'], array_map(static fn(Parameter $p) => $p->getData(), $customSequencer->sort($customGroupParams)));
    }

    /**
     * New format: a base size followed by a parenthesized range/unit suffix, e.g.
     * "L (43 – 45)" or "XL (45,5 a více )". Each suffixed variant must sort immediately
     * after its bare base size and before the next base size, so within a size group the
     * base sizes still land in logical order and each one's variants are grouped under it.
     */
    public function testSortsSizesWithParentheticalRangeSuffixes(): void
    {
        $group = $this->group(self::SIZE_GROUP_ID);
        $parameters = $this->parameters($group, [
            'L',
            'L (43 – 45)',
            'L (8.5 – 9.5 cm)',
            'M',
            'M (36 – 38,5)',
            'M (39 – 42,5)',
            'M (39 – 42.5)',
            'M (7.5 – 8.5 cm)',
            'M/L',
            'S',
            'S (36 – 38,5)',
            'S (36 – 38.5)',
            'S (6.5 – 7.5 cm)',
            'XL',
            'XL (45,5 a více )',
            'XS',
            'XS (33 – 33.5)',
            'XS/S',
        ]);

        $sorted = $this->sequencer->sort($parameters);

        $this->assertSame(
            [
                'XS',
                'XS (33 – 33.5)',
                'XS/S',
                'S',
                'S (36 – 38,5)',
                'S (36 – 38.5)',
                'S (6.5 – 7.5 cm)',
                'M',
                'M (36 – 38,5)',
                'M (39 – 42,5)',
                'M (39 – 42.5)',
                'M (7.5 – 8.5 cm)',
                'M/L',
                'L',
                'L (43 – 45)',
                'L (8.5 – 9.5 cm)',
                'XL',
                'XL (45,5 a více )',
            ],
            array_map(static fn(Parameter $p) => $p->getData(), $sorted),
        );
    }

    public function testBareSizeSortsBeforeItsOwnParentheticalVariants(): void
    {
        $group = $this->group(self::SIZE_GROUP_ID);
        $parameters = $this->parameters($group, ['S (36 – 38,5)', 'S']);

        $sorted = $this->sequencer->sort($parameters);

        $this->assertSame(['S', 'S (36 – 38,5)'], array_map(static fn(Parameter $p) => $p->getData(), $sorted));
    }

    private function group(int $id): ParameterGroup
    {
        $group = new ParameterGroup();
        $idProperty = new ReflectionProperty($group, 'id');
        $idProperty->setValue($group, $id);

        return $group;
    }

    /**
     * @param string[] $values
     * @return Parameter[]
     */
    private function parameters(ParameterGroup $group, array $values): array
    {
        return array_map(fn(string $value) => $this->parameter($group, $value), $values);
    }

    private function parameter(ParameterGroup $group, string $value): Parameter
    {
        return (new Parameter())->setData($value)->setParameterGroup($group);
    }
}
