<?php

namespace Tests\Feature;

use App\Filament\Resources\Trades\Pages\ManageTrades;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ManageTradesImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_import_uses_fallback_code_when_missing_in_json(): void
    {
        Http::fake([
            'qt.gtimg.cn/*' => Http::response('v_sh601166="兴业银行"', 200),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $jsonData = json_encode([
            'trades' => [
                [
                    // Missing 'code'
                    'name' => '興業銀行',
                    'quantity' => 100,
                    'price' => 10.5,
                    'time' => '2026-01-01 09:30:00',
                    'side' => 'buy',
                ],
            ],
        ]);

        Livewire::test(ManageTrades::class)
            ->callAction('bulkImport', [
                'json_data' => $jsonData,
                'fallback_code' => '601166',
            ])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('stocks', [
            'code' => 'sh601166',
        ]);

        $this->assertDatabaseHas('trades', [
            'quantity' => 100,
            'price' => 10.5,
            'side' => 'buy',
        ]);

        $stock = Stock::where('code', 'sh601166')->first();
        $this->assertDatabaseHas('trades', [
            'stock_id' => $stock->id,
        ]);
    }

    public function test_bulk_import_prefers_json_code_over_fallback_code(): void
    {
        Http::fake([
            'qt.gtimg.cn/*' => Http::response('v_sh600036="招商银行"', 200),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $jsonData = json_encode([
            'trades' => [
                [
                    'code' => '600036',
                    'name' => '招商银行',
                    'quantity' => 100,
                    'price' => 10.5,
                    'time' => '2026-01-01 09:30:00',
                    'side' => 'buy',
                ],
            ],
        ]);

        Livewire::test(ManageTrades::class)
            ->callAction('bulkImport', [
                'json_data' => $jsonData,
                'fallback_code' => '601166',
            ])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('stocks', [
            'code' => 'sh600036',
        ]);

        $this->assertDatabaseMissing('stocks', [
            'code' => 'sh601166',
        ]);
    }
}
