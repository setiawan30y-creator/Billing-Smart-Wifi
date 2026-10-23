<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_parse_and_arithmetic(): void
    {
        $this->assertSame('150000.00', Money::parse('150.000'));
        $this->assertSame('1500000.00', Money::parse('Rp1.500.000'));
        $this->assertSame('150000.50', Money::parse('150000,50'));
        $this->assertSame('0.30', Money::add('0.1', '0.2'));
        $this->assertSame('600.00', Money::mul('300', '2'));
        $this->assertSame('Rp150.000', Money::format('150000.00'));
    }
}
