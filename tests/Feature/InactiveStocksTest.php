<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Stock;
use App\Models\Trade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InactiveStocksTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_stocks_scope_only_includes_stocks_whose_latest_trade_is_older_than_threshold(): void
    {
        // Set threshold to 30 days
        AppSetting::set('inactive_stocks_threshold', 30);
        $threshold = 30;

        // 1. Inactive stock: latest trade is 35 days ago
        $inactiveStock = Stock::factory()->create(['name' => 'Inactive Stock', 'type' => 'stock']);
        Trade::factory()->create([
            'stock_id' => $inactiveStock->id,
            'executed_at' => Carbon::now()->subDays($threshold + 5),
            'created_at' => Carbon::now(), // Different from executed_at
        ]);

        // 2. Active stock: has an old trade, but also a recent trade
        $activeStock = Stock::factory()->create(['name' => 'Active Stock', 'type' => 'stock']);
        Trade::factory()->create([
            'stock_id' => $activeStock->id,
            'executed_at' => Carbon::now()->subDays(40),
        ]);
        Trade::factory()->create([
            'stock_id' => $activeStock->id,
            'executed_at' => Carbon::now()->subDays(5),
        ]);

        // 3. New stock: no trades at all
        $newStock = Stock::factory()->create(['name' => 'New Stock', 'type' => 'stock']);

        // 4. Index stock: old trade, but should be excluded by type
        $indexStock = Stock::factory()->create(['name' => 'Index Stock', 'type' => 'index']);
        Trade::factory()->create([
            'stock_id' => $indexStock->id,
            'executed_at' => Carbon::now()->subDays(40),
        ]);

        $inactiveStocks = Stock::inactiveStocks()->get();

        $this->assertCount(1, $inactiveStocks);
        $this->assertEquals('Inactive Stock', $inactiveStocks->first()->name);

        // Verify isInactive() method
        $this->assertTrue($inactiveStock->isInactive());
        $this->assertFalse($activeStock->isInactive());
        $this->assertFalse($newStock->isInactive());
        $this->assertTrue($indexStock->isInactive()); // isInactive() doesn't check type, but the scope does
    }

    public function test_inactive_stocks_uses_executed_at_instead_of_created_at(): void
    {
        AppSetting::set('inactive_stocks_threshold', 30);
        $threshold = 30;

        // Stock with recent created_at but old executed_at should be INACTIVE
        $stock = Stock::factory()->create(['type' => 'stock']);
        Trade::factory()->create([
            'stock_id' => $stock->id,
            'executed_at' => Carbon::now()->subDays(40),
            'created_at' => Carbon::now(), // Recent created_at
        ]);

        $this->assertTrue($stock->isInactive());
        $this->assertCount(1, Stock::inactiveStocks()->get());

        // Reset and check opposite: old created_at but recent executed_at should be ACTIVE
        Trade::query()->delete();
        Trade::factory()->create([
            'stock_id' => $stock->id,
            'executed_at' => Carbon::now()->subDays(5),
            'created_at' => Carbon::now()->subDays(40), // Old created_at
        ]);

        $this->assertFalse($stock->isInactive());
        $this->assertCount(0, Stock::inactiveStocks()->get());
    }
}
