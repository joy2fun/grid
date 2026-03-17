<?php

namespace Tests\Unit;

use App\Models\Holding;
use App\Models\Stock;
use App\Models\Trade;
use App\Services\PortfolioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PortfolioServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculate_overall_xirr_returns_null_when_no_trades(): void
    {
        $result = PortfolioService::calculateOverallXirr();

        $this->assertNull($result);
    }

    public function test_calculate_overall_xirr_with_trades_and_holding(): void
    {
        $stock = Stock::factory()->create([
            'current_price' => 120,
        ]);

        // Create buy trade - TradeObserver will auto-create holding
        Trade::factory()->create([
            'stock_id' => $stock->id,
            'type' => 'buy',
            'price' => 100,
            'quantity' => 10,
            'executed_at' => now()->subYear(),
        ]);

        // Update the auto-created holding to ensure correct values
        Holding::updateOrCreate(
            ['stock_id' => $stock->id],
            [
                'quantity' => 10,
                'initial_quantity' => 0,
                'initial_cost' => 0,
                'average_cost' => 100,
                'total_cost' => 1000,
            ]
        );

        $result = PortfolioService::calculateOverallXirr();

        $this->assertNotNull($result);
        // XIRR should be positive since current price (120) > buy price (100)
        $this->assertGreaterThan(0, $result);
    }

    public function test_calculate_overall_xirr_with_sell_trade(): void
    {
        $stock = Stock::factory()->create([
            'current_price' => 100,
        ]);

        // Create buy trade - TradeObserver will auto-create holding
        Trade::factory()->create([
            'stock_id' => $stock->id,
            'type' => 'buy',
            'price' => 100,
            'quantity' => 10,
            'executed_at' => now()->subMonths(6),
        ]);

        // Create sell trade (partial)
        Trade::factory()->create([
            'stock_id' => $stock->id,
            'type' => 'sell',
            'price' => 110,
            'quantity' => 5,
            'executed_at' => now()->subMonth(),
        ]);

        // Update the auto-created holding with remaining shares
        Holding::updateOrCreate(
            ['stock_id' => $stock->id],
            [
                'quantity' => 5,
                'initial_quantity' => 0,
                'initial_cost' => 0,
                'average_cost' => 100,
                'total_cost' => 500,
            ]
        );

        $result = PortfolioService::calculateOverallXirr();

        $this->assertNotNull($result);
    }

    public function test_xirr_cache_is_used(): void
    {
        $stock = Stock::factory()->create([
            'current_price' => 120,
        ]);

        Trade::factory()->create([
            'stock_id' => $stock->id,
            'type' => 'buy',
            'price' => 100,
            'quantity' => 10,
            'executed_at' => now()->subYear(),
        ]);

        Holding::updateOrCreate(
            ['stock_id' => $stock->id],
            [
                'quantity' => 10,
                'initial_quantity' => 0,
                'initial_cost' => 0,
                'average_cost' => 100,
                'total_cost' => 1000,
            ]
        );

        // First call should cache the result
        $firstResult = PortfolioService::calculateOverallXirr();

        // Second call should return cached value
        $secondResult = PortfolioService::calculateOverallXirr();

        $this->assertEquals($firstResult, $secondResult);
        $this->assertTrue(Cache::has('portfolio_overall_xirr'));
    }

    public function test_clear_xirr_cache_works(): void
    {
        $stock = Stock::factory()->create([
            'current_price' => 120,
        ]);

        Trade::factory()->create([
            'stock_id' => $stock->id,
            'type' => 'buy',
            'price' => 100,
            'quantity' => 10,
            'executed_at' => now()->subYear(),
        ]);

        Holding::updateOrCreate(
            ['stock_id' => $stock->id],
            [
                'quantity' => 10,
                'initial_quantity' => 0,
                'initial_cost' => 0,
                'average_cost' => 100,
                'total_cost' => 1000,
            ]
        );

        PortfolioService::calculateOverallXirr();
        $this->assertTrue(Cache::has('portfolio_overall_xirr'));

        PortfolioService::clearXirrCache();
        $this->assertFalse(Cache::has('portfolio_overall_xirr'));
    }
}
