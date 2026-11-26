<?php

namespace Tests\Unit;

use App\Services\Properties\ComplexInventoryService as Inventory;
use PHPUnit\Framework\TestCase;

class PropertyKindsTest extends TestCase
{
    public function test_the_import_composition_column_names_kinds_in_a_fixed_order(): void
    {
        $this->assertSame(['houses'], Inventory::kindsFromText('houses'));
        $this->assertSame(['apartments', 'commercial'], Inventory::kindsFromText('Commercial, apartments'));
        $this->assertSame(['apartments', 'houses', 'commercial'], Inventory::kindsFromText('house and apartment / commercial'));
        $this->assertSame(['apartments', 'commercial'], Inventory::kindsFromText('combined'), 'older values still work');
        $this->assertSame(['apartments'], Inventory::kindsFromText('residential'));
    }

    public function test_anything_unknown_is_rejected(): void
    {
        $this->assertSame([], Inventory::kindsFromText(''));
        $this->assertSame([], Inventory::kindsFromText('houses, offices'));
        $this->assertSame([], Inventory::kindsFromText('villa'));
    }
}
