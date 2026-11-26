<?php

namespace Tests\Unit;

use App\Services\Properties\PropertyExpenseWorkflow;
use PHPUnit\Framework\TestCase;

class PropertyExpenseWorkflowTest extends TestCase
{
    public function test_decimal_values_are_compared_in_minor_units(): void
    {
        $this->assertSame(10101, PropertyExpenseWorkflow::cents('101.01'));
        $this->assertSame(5050, PropertyExpenseWorkflow::cents('50.5'));
        $this->assertSame(1, PropertyExpenseWorkflow::cents('0.01'));
        $this->assertSame(100, PropertyExpenseWorkflow::cents('1'));
        $this->assertSame(99999999900, PropertyExpenseWorkflow::cents('999999999.00'));
    }

    public function test_partial_payments_leave_an_exact_balance(): void
    {
        $cost = PropertyExpenseWorkflow::cents('101.01');
        $paid = PropertyExpenseWorkflow::cents('50.51');
        $this->assertSame(5050, $cost - $paid);
        $this->assertSame(0, $cost - $paid - PropertyExpenseWorkflow::cents('50.50'));
    }
}
