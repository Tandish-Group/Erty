<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class InventoryPaginationTest extends TestCase
{
    public function test_inventory_pages_explicitly_use_bootstrap_pagination(): void
    {
        foreach (['residential-inventory', 'commercial_units/index', 'journey', 'partials/inventory'] as $view) {
            $source = file_get_contents(resource_path('views/pages/business/complex/'.$view.'.blade.php'));
            $this->assertStringContainsString("->links('pagination::bootstrap-5')", $source);
            $this->assertStringNotContainsString('->links()', $source);
        }
    }

    public function test_second_page_has_bootstrap_controls_without_svg_and_preserves_filters(): void
    {
        // Render only pagination, without application-wide database-backed view composers.
        $this->app['view']->setDispatcher(new \Illuminate\Events\Dispatcher($this->app));
        $paginator = new LengthAwarePaginator(range(26, 42), 42, 25, 2, ['path' => '/residential-units']);
        $paginator->appends(['complex_id' => 5, 'status' => 'available']);
        $html = (string) $paginator->links('pagination::bootstrap-5');
        $this->assertStringContainsString('class="pagination"', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('complex_id=5', $html);
        $this->assertStringContainsString('status=available', $html);
        $this->assertStringContainsString('page=1', $html);
        $this->assertStringNotContainsString('<svg', $html);
        $this->assertStringNotContainsString('w-5 h-5', $html);
        $this->assertFalse($paginator->hasMorePages());
    }
}
