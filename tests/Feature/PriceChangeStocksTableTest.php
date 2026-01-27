<?php

namespace Tests\Feature;

use App\Filament\Widgets\PriceChangeStocksTable;
use App\Models\AppSetting;
use App\Models\Stock;
use App\Models\Trade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class PriceChangeStocksTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_change_calculation_uses_executed_at_latest_trade(): void
    {
        AppSetting::set('price_change_threshold', 5);

        $stock = Stock::factory()->create(['current_price' => 100, 'type' => 'stock']);

        // Old trade, low price (would cause high change if used)
        Trade::factory()->create([
            'stock_id' => $stock->id,
            'price' => 50,
            'executed_at' => Carbon::now()->subDays(10),
        ]);

        // Latest trade, price close to current (should be used, small change)
        Trade::factory()->create([
            'stock_id' => $stock->id,
            'price' => 99,
            'executed_at' => Carbon::now()->subDays(1),
        ]);

        // 1% change, should NOT appear in table (threshold 5%)
        Livewire::test(PriceChangeStocksTable::class)
            ->assertCanNotSeeTableRecords([$stock]);

        // Now invert it: Latest trade causes HIGH change
        $stock2 = Stock::factory()->create(['current_price' => 100, 'type' => 'stock']);
        Trade::factory()->create([
            'stock_id' => $stock2->id,
            'price' => 99,
            'executed_at' => Carbon::now()->subDays(10),
        ]);
        Trade::factory()->create([
            'stock_id' => $stock2->id,
            'price' => 50, // 100 vs 50 = +100% change
            'executed_at' => Carbon::now()->subDays(1),
        ]);

        Livewire::test(PriceChangeStocksTable::class)
            ->assertCanSeeTableRecords([$stock2]);
    }

    public function test_sorting_by_price_change(): void
    {
        AppSetting::set('price_change_threshold', 1);

        // Stock A: 10% change
        $stockA = Stock::factory()->create(['name' => 'StockA', 'current_price' => 110, 'type' => 'stock']);
        Trade::factory()->create(['stock_id' => $stockA->id, 'price' => 100, 'executed_at' => now()]);

        // Stock B: 50% change
        $stockB = Stock::factory()->create(['name' => 'StockB', 'current_price' => 150, 'type' => 'stock']);
        Trade::factory()->create(['stock_id' => $stockB->id, 'price' => 100, 'executed_at' => now()]);

        // Stock C: 5% change
        $stockC = Stock::factory()->create(['name' => 'StockC', 'current_price' => 105, 'type' => 'stock']);
        Trade::factory()->create(['stock_id' => $stockC->id, 'price' => 100, 'executed_at' => now()]);

        // Default sort is descending by priceChange
        // Expected order: B (50%), A (10%), C (5%)

        // We can check the order of records in the table
        Livewire::test(PriceChangeStocksTable::class)
            ->assertCanSeeTableRecords([$stockA, $stockB, $stockC])
            ->assertSeeInOrder(['StockB', 'StockA', 'StockC']);
    }
}
