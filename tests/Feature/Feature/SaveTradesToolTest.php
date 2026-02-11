<?php

namespace Tests\Feature;

use App\Mcp\Servers\GridTradingServer;
use App\Mcp\Tools\SaveTradesTool;
use App\Models\Grid;
use App\Models\Stock;
use App\Models\Trade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaveTradesToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_save_single_trade(): void
    {
        $response = GridTradingServer::tool(SaveTradesTool::class, [
            'trades' => [
                [
                    'code' => '600036',
                    'type' => 'buy',
                    'quantity' => 100,
                    'price' => '10.5',
                    'time' => '2026-01-01 13:22:22',
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('stocks', [
            'code' => '600036',
        ]);

        $this->assertDatabaseHas('trades', [
            'type' => 'buy',
            'quantity' => 100,
            'price' => '10.5',
        ]);

        $this->assertEquals(1, Trade::count());
    }

    public function test_can_save_multiple_trades(): void
    {
        $response = GridTradingServer::tool(SaveTradesTool::class, [
            'trades' => [
                [
                    'code' => '600036',
                    'type' => 'buy',
                    'quantity' => 100,
                    'price' => '10.5',
                    'time' => '2026-01-01 13:22:22',
                ],
                [
                    'code' => '600036',
                    'type' => 'sell',
                    'quantity' => 50,
                    'price' => '11.0',
                    'time' => '2026-01-02 14:30:00',
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertEquals(2, Trade::count());
        $this->assertEquals(1, Stock::count());
    }

    public function test_creates_stock_if_not_exists(): void
    {
        $this->assertEquals(0, Stock::count());

        $response = GridTradingServer::tool(SaveTradesTool::class, [
            'trades' => [
                [
                    'code' => 'AAPL',
                    'type' => 'buy',
                    'quantity' => 10,
                    'price' => '150.00',
                    'time' => '2026-01-01 10:00:00',
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertEquals(1, Stock::count());
        $this->assertDatabaseHas('stocks', [
            'code' => 'AAPL',
            'name' => 'AAPL',
        ]);
    }

    public function test_reuses_existing_stock(): void
    {
        $stock = Stock::factory()->create(['code' => '600036', 'name' => 'China Merchants Bank']);

        $response = GridTradingServer::tool(SaveTradesTool::class, [
            'trades' => [
                [
                    'code' => '600036',
                    'type' => 'buy',
                    'quantity' => 100,
                    'price' => '10.5',
                    'time' => '2026-01-01 13:22:22',
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertEquals(1, Stock::count());

        $trade = Trade::first();
        $this->assertEquals($stock->id, $trade->stock_id);
    }

    public function test_can_save_trade_with_grid_id(): void
    {
        $stock = Stock::factory()->create(['code' => '600036']);
        $grid = Grid::factory()->create(['stock_id' => $stock->id]);

        $response = GridTradingServer::tool(SaveTradesTool::class, [
            'trades' => [
                [
                    'code' => '600036',
                    'type' => 'buy',
                    'quantity' => 100,
                    'price' => '10.5',
                    'time' => '2026-01-01 13:22:22',
                    'grid_id' => $grid->id,
                ],
            ],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('trades', [
            'grid_id' => $grid->id,
        ]);
    }

    public function test_validation_fails_for_missing_trades_array(): void
    {
        $response = GridTradingServer::tool(SaveTradesTool::class, []);

        $response->assertHasErrors();
    }

    public function test_validation_fails_for_invalid_type(): void
    {
        $response = GridTradingServer::tool(SaveTradesTool::class, [
            'trades' => [
                [
                    'code' => '600036',
                    'type' => 'invalid',
                    'quantity' => 100,
                    'price' => '10.5',
                    'time' => '2026-01-01 13:22:22',
                ],
            ],
        ]);

        $response->assertHasErrors();
    }

    public function test_validation_fails_for_negative_quantity(): void
    {
        $response = GridTradingServer::tool(SaveTradesTool::class, [
            'trades' => [
                [
                    'code' => '600036',
                    'type' => 'buy',
                    'quantity' => -10,
                    'price' => '10.5',
                    'time' => '2026-01-01 13:22:22',
                ],
            ],
        ]);

        $response->assertHasErrors();
    }

    public function test_validation_fails_for_invalid_grid_id(): void
    {
        $response = GridTradingServer::tool(SaveTradesTool::class, [
            'trades' => [
                [
                    'code' => '600036',
                    'type' => 'buy',
                    'quantity' => 100,
                    'price' => '10.5',
                    'time' => '2026-01-01 13:22:22',
                    'grid_id' => 99999,
                ],
            ],
        ]);

        $response->assertHasErrors();
    }

    public function test_returns_structured_response_with_saved_count(): void
    {
        $response = GridTradingServer::tool(SaveTradesTool::class, [
            'trades' => [
                [
                    'code' => '600036',
                    'type' => 'buy',
                    'quantity' => 100,
                    'price' => '10.5',
                    'time' => '2026-01-01 13:22:22',
                ],
                [
                    'code' => '600037',
                    'type' => 'sell',
                    'quantity' => 50,
                    'price' => '20.0',
                    'time' => '2026-01-02 10:00:00',
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertSee('Successfully saved 2 out of 2 trades');
    }
}
