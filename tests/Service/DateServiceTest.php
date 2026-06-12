<?php

namespace Greendot\EshopBundle\Tests\Service;

use DateTime;
use Greendot\EshopBundle\Service\DateService;
use PHPUnit\Framework\TestCase;

class DateServiceTest extends TestCase
{
    private DateService $dateService;

    protected function setUp(): void
    {
        $this->dateService = new DateService();
    }

    public function testAddOneWorkDayFromFridayLandsOnMonday(): void
    {
        $friday = new DateTime('2025-01-03'); // Jan 3 2025 is a Friday
        $result = $this->dateService->addWorkDays($friday, 1);

        $this->assertSame('2025-01-06', $result->format('Y-m-d'));
        $this->assertSame('Monday', $result->format('l'));
    }

    public function testAddThreeWorkDaysAcrossWeekend(): void
    {
        // Friday + 3 work days: Mon, Tue, Wed
        $friday = new DateTime('2025-01-03');
        $result = $this->dateService->addWorkDays($friday, 3);

        $this->assertSame('2025-01-08', $result->format('Y-m-d'));
    }

    public function testAddWorkDaysWithNoWeekendInBetween(): void
    {
        // Monday + 2 work days = Wednesday
        $monday = new DateTime('2025-01-06');
        $result = $this->dateService->addWorkDays($monday, 2);

        $this->assertSame('2025-01-08', $result->format('Y-m-d'));
    }

    public function testAddZeroWorkDaysReturnsSameDate(): void
    {
        $date = new DateTime('2025-01-06');
        $result = $this->dateService->addWorkDays($date, 0);

        $this->assertSame('2025-01-06', $result->format('Y-m-d'));
    }

    public function testInputDateIsNotMutated(): void
    {
        $date = new DateTime('2025-01-03');
        $this->dateService->addWorkDays($date, 5);

        $this->assertSame('2025-01-03', $date->format('Y-m-d'), 'addWorkDays must not modify the original DateTime');
    }
}
