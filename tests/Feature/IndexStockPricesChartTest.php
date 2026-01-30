<?php

namespace Tests\Feature;

use App\Models\DayPrice;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IndexStockPricesChartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Authenticate user for Filament access
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    public function test_index_stock_prices_chart_renders_with_index_stocks(): void
    {
        // Create index stocks
        $stock1 = Stock::factory()->create([
            'code' => 'hkHSI',
            'name' => 'Hang Seng Index',
            'type' => 'index',
        ]);

        $stock2 = Stock::factory()->create([
            'code' => 'sh000001',
            'name' => 'Shanghai Composite',
            'type' => 'index',
        ]);

        // Create non-index stock (should not appear in chart)
        Stock::factory()->create([
            'code' => '510300',
            'name' => 'CSI 300 ETF',
            'type' => 'etf',
        ]);

        // Create day prices for index stocks
        DayPrice::factory()->create([
            'stock_id' => $stock1->id,
            'date' => now()->subDays(2),
            'close_price' => 18500.50,
        ]);

        DayPrice::factory()->create([
            'stock_id' => $stock1->id,
            'date' => now()->subDays(1),
            'close_price' => 18600.75,
        ]);

        DayPrice::factory()->create([
            'stock_id' => $stock2->id,
            'date' => now()->subDays(2),
            'close_price' => 3200.25,
        ]);

        DayPrice::factory()->create([
            'stock_id' => $stock2->id,
            'date' => now()->subDays(1),
            'close_price' => 3250.50,
        ]);

        // Test the widget can render
        $widget = Livewire::test(\App\Filament\Widgets\IndexStockPricesChart::class);

        $widget->assertOk();
    }

    public function test_chart_renders_with_empty_index_stocks(): void
    {
        // Create only non-index stocks
        Stock::factory()->create([
            'code' => '510300',
            'name' => 'CSI 300 ETF',
            'type' => 'etf',
        ]);

        // Test the widget can render even with no index stocks
        $widget = Livewire::test(\App\Filament\Widgets\IndexStockPricesChart::class);

        $widget->assertOk();
    }
}
