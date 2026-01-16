<?php

namespace Tests\Feature;

use App\Models\DayPrice;
use App\Models\Grid;
use App\Models\Stock;
use App\Models\Trade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GridMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_grid_metrics_calculation(): void
    {
        // 1. Setup
        $stock = Stock::factory()->create([
            'code' => '600000',
            'name' => 'Test Stock',
        ]);

        // Create day prices
        DayPrice::factory()->create([
            'stock_id' => $stock->id,
            'date' => '2023-01-01',
            'close_price' => 10.0,
        ]);

        // This will be the "current" price (latest)
        DayPrice::factory()->create([
            'stock_id' => $stock->id,
            'date' => '2023-01-02',
            'close_price' => 11.0,
        ]);

        $grid = Grid::factory()->create([
            'stock_id' => $stock->id,
            'name' => 'Test Grid',
            'initial_amount' => 10000,
        ]);

        // 2. Add trades
        // Buy 1000 shares at 10.0 on 2023-01-01
        Trade::factory()->create([
            'grid_id' => $grid->id,
            'stock_id' => $stock->id,
            'side' => 'buy',
            'price' => 10.0,
            'quantity' => 1000,
            'executed_at' => '2023-01-01 10:00:00',
        ]);

        // 3. Calculate metrics
        $metrics = $grid->getMetrics();

        // 4. Assertions
        // Current price is 11.0
        // Holding: 1000 shares * 11.0 = 11000
        // Net Cash: -10000
        // Total Profit: 11000 - 10000 = 1000
        // Max Cash Occupied: 10000

        $this->assertEquals(1000.0, (float) $metrics['total_profit']);
        $this->assertEquals(-10000.0, (float) $metrics['net_cash']);
        $this->assertEquals(11000.0, (float) $metrics['holding_value']);
        $this->assertEquals(10000.0, (float) $metrics['max_cash_occupied']);
        $this->assertEquals(1, $metrics['trades_count']);
        $this->assertEquals(1000, $metrics['final_shares']);
        $this->assertEquals(11.0, (float) $metrics['final_price']);

        // XIRR should be positive (10% return in 1 day, annualized is huge)
        $this->assertNotNull($metrics['xirr']);
        $this->assertGreaterThan(0, $metrics['xirr']);
    }

    public function test_grid_metrics_with_multiple_trades(): void
    {
        $stock = Stock::factory()->create();

        DayPrice::factory()->create([
            'stock_id' => $stock->id,
            'date' => '2023-01-01',
            'close_price' => 10.0,
        ]);

        DayPrice::factory()->create([
            'stock_id' => $stock->id,
            'date' => '2023-01-02',
            'close_price' => 9.0,
        ]);

        DayPrice::factory()->create([
            'stock_id' => $stock->id,
            'date' => '2023-01-03',
            'close_price' => 9.5,
        ]);

        $grid = Grid::factory()->create(['stock_id' => $stock->id]);

        // Buy 100 at 10.0
        Trade::factory()->create([
            'grid_id' => $grid->id,
            'stock_id' => $stock->id,
            'side' => 'buy',
            'price' => 10.0,
            'quantity' => 100,
            'executed_at' => '2023-01-01 10:00:00',
        ]);

        // Buy 100 at 9.0
        Trade::factory()->create([
            'grid_id' => $grid->id,
            'stock_id' => $stock->id,
            'side' => 'buy',
            'price' => 9.0,
            'quantity' => 100,
            'executed_at' => '2023-01-02 10:00:00',
        ]);

        $metrics = $grid->getMetrics();

        // Current price 9.5
        // Cash: -1000 - 900 = -1900
        // Shares: 200
        // Holding Value: 200 * 9.5 = 1900
        // Total Profit: 1900 - 1900 = 0

        $this->assertEquals(0.0, (float) $metrics['total_profit']);
        $this->assertEquals(-1900.0, (float) $metrics['net_cash']);
        $this->assertEquals(1900.0, (float) $metrics['max_cash_occupied']);
    }
}
