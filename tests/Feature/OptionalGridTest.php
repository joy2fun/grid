<?php

namespace Tests\Feature;

use App\Models\Stock;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OptionalGridTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_trade_without_grid(): void
    {
        $stock = Stock::factory()->create();

        $trade = Trade::create([
            'stock_id' => $stock->id,
            'side' => 'buy',
            'price' => 100,
            'quantity' => 10,
            'executed_at' => now(),
        ]);

        $this->assertNull($trade->grid_id);
        $this->assertDatabaseHas('trades', ['id' => $trade->id]);
    }
}
