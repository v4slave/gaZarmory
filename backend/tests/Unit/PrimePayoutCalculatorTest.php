<?php

namespace Tests\Unit;

use App\Services\PrimePayoutCalculator;
use PHPUnit\Framework\TestCase;

final class PrimePayoutCalculatorTest extends TestCase
{
    public function test_it_floors_each_share_and_keeps_remainder(): void
    {
        $result = (new PrimePayoutCalculator())->calculate(20_000, 60);
        self::assertSame(333, $result['player_share']);
        self::assertSame(19_980, $result['distributed']);
        self::assertSame(20, $result['remainder']);
    }
}
