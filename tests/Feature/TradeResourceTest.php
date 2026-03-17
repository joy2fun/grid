<?php

namespace Tests\Feature;

use App\Filament\Resources\Trades\Pages\ManageTrades;
use App\Filament\Resources\Trades\TradeResource;
use App\Models\Stock;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TradeResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_render_trade_resource_index_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(TradeResource::getUrl('index'))
            ->assertSuccessful();
    }

    public function test_can_export_xirr_cashflow_csv(): void
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['current_price' => 15.00]);

        // Create buy trade - observer will auto-create holding
        Trade::factory()->create([
            'stock_id' => $stock->id,
            'type' => 'buy',
            'quantity' => 100,
            'price' => 10.00,
            'executed_at' => '2024-01-01',
        ]);

        // Create sell trade
        Trade::factory()->create([
            'stock_id' => $stock->id,
            'type' => 'sell',
            'quantity' => 50,
            'price' => 12.00,
            'executed_at' => '2024-02-01',
        ]);

        Livewire::actingAs($user)
            ->test(ManageTrades::class)
            ->callAction('exportXirrCashflow')
            ->assertSuccessful();
    }

    public function test_can_export_xirr_cashflow_with_stock_filter(): void
    {
        $user = User::factory()->create();
        $stockA = Stock::factory()->create(['current_price' => 15.00]);
        $stockB = Stock::factory()->create(['current_price' => 20.00]);

        // Create trades for stock A
        Trade::factory()->create([
            'stock_id' => $stockA->id,
            'type' => 'buy',
            'quantity' => 100,
            'price' => 10.00,
            'executed_at' => '2024-01-01',
        ]);

        // Create trades for stock B
        Trade::factory()->create([
            'stock_id' => $stockB->id,
            'type' => 'buy',
            'quantity' => 200,
            'price' => 5.00,
            'executed_at' => '2024-01-15',
        ]);

        // Export with stock filter applied
        Livewire::actingAs($user)
            ->test(ManageTrades::class)
            ->set('tableFilters.stock_id.value', $stockA->id)
            ->callAction('exportXirrCashflow')
            ->assertSuccessful();
    }
}
