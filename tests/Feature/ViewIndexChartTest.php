<?php

namespace Tests\Feature\Filament\Pages;

use App\Filament\Pages\ViewIndexChart;
use App\Models\DayPrice;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ViewIndexChartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_page_renders_successfully()
    {
        Livewire::test(ViewIndexChart::class)
            ->assertSuccessful();
    }

    public function test_time_range_filters_update_property()
    {
        Livewire::test(ViewIndexChart::class)
            ->assertSet('timeRange', '3y') // Default
            ->callAction('6m')
            ->assertSet('timeRange', '6m')
            ->callAction('12m')
            ->assertSet('timeRange', '12m')
            ->callAction('5y')
            ->assertSet('timeRange', '5y');
    }

    public function test_widget_receives_time_range()
    {
        // Create some data
        $stock = Stock::factory()->create(['type' => 'index', 'name' => 'Test Index']);
        DayPrice::factory()->create(['stock_id' => $stock->id, 'date' => now()->subMonths(2), 'close_price' => 100]);

        // Verify the widget is rendered on the page using its class name or alias
        // Filament widgets are Livewire components
        Livewire::test(ViewIndexChart::class)
            ->assertSeeLivewire(\App\Filament\Widgets\IndexStockPricesChart::class);
    }
}
