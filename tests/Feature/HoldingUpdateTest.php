<?php

namespace Tests\Feature;

use App\Models\Holding;
use App\Models\Stock;
use App\Models\Trade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HoldingUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_holding_is_created_when_trade_is_created(): void
    {
        $stock = Stock::factory()->create();

        Trade::factory()->create([
            'stock_id' => $stock->id,
            'side' => 'buy',
            'quantity' => 10,
            'price' => 100,
        ]);

        $holding = Holding::where('stock_id', $stock->id)->first();

        $this->assertNotNull($holding);
        $this->assertEquals(10, $holding->quantity);
        $this->assertEquals(100, $holding->average_cost);
        $this->assertEquals(1000, $holding->total_cost);
    }

    public function test_holding_is_updated_when_multiple_trades_are_created(): void
    {
        $stock = Stock::factory()->create();

        Trade::factory()->create([
            'stock_id' => $stock->id,
            'side' => 'buy',
            'quantity' => 10,
            'price' => 100,
        ]);

        Trade::factory()->create([
            'stock_id' => $stock->id,
            'side' => 'buy',
            'quantity' => 5,
            'price' => 110,
        ]);

        $holding = Holding::where('stock_id', $stock->id)->first();

        $this->assertEquals(15, $holding->quantity);
        // (10 * 100 + 5 * 110) / 15 = (1000 + 550) / 15 = 1550 / 15 = 103.33333333
        $this->assertEqualsWithDelta(103.33333333, $holding->average_cost, 0.00000001);
        $this->assertEquals(1550, $holding->total_cost);
    }

    public function test_holding_is_updated_when_sell_trade_is_created(): void
    {
        $stock = Stock::factory()->create();

        Trade::factory()->create([
            'stock_id' => $stock->id,
            'side' => 'buy',
            'quantity' => 10,
            'price' => 100,
        ]);

        Trade::factory()->create([
            'stock_id' => $stock->id,
            'side' => 'sell',
            'quantity' => 4,
            'price' => 120,
        ]);

        $holding = Holding::where('stock_id', $stock->id)->first();

        $this->assertEquals(6, $holding->quantity);
        // (1000 - 4 * 120) = 1000 - 480 = 520
        // 520 / 6 = 86.66666667
        $this->assertEqualsWithDelta(86.66666667, $holding->average_cost, 0.00000001);
        $this->assertEquals(520, $holding->total_cost);
    }

    public function test_holding_is_recalculated_when_trade_is_deleted(): void
    {
        $stock = Stock::factory()->create();

        $trade1 = Trade::factory()->create([
            'stock_id' => $stock->id,
            'side' => 'buy',
            'quantity' => 10,
            'price' => 100,
        ]);

        $trade2 = Trade::factory()->create([
            'stock_id' => $stock->id,
            'side' => 'buy',
            'quantity' => 5,
            'price' => 110,
        ]);

        $trade1->delete();

        $holding = Holding::where('stock_id', $stock->id)->first();

        $this->assertEquals(5, $holding->quantity);
        $this->assertEquals(110, $holding->average_cost);
        $this->assertEquals(550, $holding->total_cost);
    }
}
