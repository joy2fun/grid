<?php

namespace Tests\Feature;

use App\Models\Holding;
use App\Models\Stock;
use App\Models\Trade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockXirrTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_xirr_calculation_with_trades_and_holding(): void
    {
        // 1. Setup stock with current price
        $stock = Stock::create([
            'code' => 'sh600000',
            'name' => 'Test Stock',
            'current_price' => 11.0,
        ]);

        // 2. Buy 1000 shares at 10.0 on 2023-01-01
        Trade::create([
            'stock_id' => $stock->id,
            'grid_id' => null,
            'type' => 'buy',
            'price' => 10.0,
            'quantity' => 1000,
            'executed_at' => '2023-01-01 10:00:00',
        ]);

        // 3. Setup holding with proper cost (TradeObserver may have created one)
        Holding::updateOrCreate(
            ['stock_id' => $stock->id],
            [
                'quantity' => 1000,
                'total_cost' => 10000.0,
                'average_cost' => 10.0,
            ]
        );

        // XIRR calculation:
        // Jan 1: -10000 (buy)
        // Today: +11000 (valuation)
        // 10% return in 1 year (if today were Jan 1 2024)
        // Given it's been several years since 2023-01-01, it will be a positive number.

        // Refresh stock to load relationships
        $stock->refresh();
        $stock->load('trades', 'holding');

        $xirr = $stock->xirr;

        $this->assertNotNull($xirr);
        $this->assertGreaterThan(0, $xirr);
    }

    public function test_stock_xirr_with_multiple_trades(): void
    {
        $stock = Stock::create([
            'code' => 'sh600001',
            'name' => 'Test Stock 2',
            'current_price' => 10.0,
        ]);

        // Buy 100 at 10.0 on Jan 1
        Trade::create([
            'stock_id' => $stock->id,
            'grid_id' => null,
            'type' => 'buy',
            'price' => 10.0,
            'quantity' => 100,
            'executed_at' => '2023-01-01 10:00:00',
        ]);

        // Buy 100 at 8.0 on Jan 2
        Trade::create([
            'stock_id' => $stock->id,
            'grid_id' => null,
            'type' => 'buy',
            'price' => 8.0,
            'quantity' => 100,
            'executed_at' => '2023-01-02 10:00:00',
        ]);

        Holding::updateOrCreate(
            ['stock_id' => $stock->id],
            [
                'quantity' => 200,
                'total_cost' => 1800.0,
                'average_cost' => 9.0,
            ]
        );

        // Current price 10.0
        // Total cost: 1000 + 800 = 1800
        // Current value: 200 * 10 = 2000
        // Profit 200, positive XIRR

        // Refresh stock to load relationships
        $stock->refresh();
        $stock->load('trades', 'holding');

        $xirr = $stock->xirr;
        $this->assertNotNull($xirr);
        $this->assertGreaterThan(0, $xirr);
    }

    public function test_stock_xirr_returns_null_with_no_trades(): void
    {
        $stock = Stock::factory()->create();
        $this->assertNull($stock->xirr);
    }
}
