<?php

namespace Tests\Feature;

use App\Filament\Resources\Trades\Pages\ManageTrades;
use App\Models\Stock;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TradeBackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());

        // Mock the external HTTP API to prevent actual calls during tests
        Http::fake([
            'qt.gtimg.cn/*' => Http::response('', 200),
        ]);
    }

    #[Test]
    public function test_can_backup_trades_to_json_file(): void
    {
        $stock = Stock::factory()->create([
            'code' => 'sh601166',
            'name' => 'Test Stock',
        ]);

        Trade::factory()->create([
            'stock_id' => $stock->id,
            'side' => 'buy',
            'quantity' => 100,
            'price' => 10.5,
            'executed_at' => now(),
        ]);

        Livewire::test(ManageTrades::class)
            ->callAction('backup')
            ->assertHasNoErrors();
    }

    #[Test]
    public function test_can_restore_trades_from_json_backup(): void
    {
        // Create a stock that will be referenced in the backup
        Stock::factory()->create([
            'code' => 'sh601166',
            'name' => 'Existing Stock',
        ]);

        $backupData = [
            'export_date' => now()->toIso8601String(),
            'total_trades' => 2,
            'trades' => [
                [
                    'stock_code' => '601166',
                    'stock_name' => 'Industrial Bank',
                    'side' => 'buy',
                    'quantity' => 100,
                    'price' => 15.5,
                    'executed_at' => now()->subDays(5)->toIso8601String(),
                ],
                [
                    'stock_code' => '000001',
                    'stock_name' => 'New Stock',
                    'side' => 'sell',
                    'quantity' => 50,
                    'price' => 20.0,
                    'executed_at' => now()->subDays(3)->toIso8601String(),
                ],
            ],
        ];

        $jsonContent = json_encode($backupData, JSON_PRETTY_PRINT);
        $file = UploadedFile::fake()->createWithContent('backup.json', $jsonContent);

        Livewire::test(ManageTrades::class)
            ->callAction('restore', [
                'backup_file' => [$file],
            ])
            ->assertHasNoErrors();

        // Assert that trades were imported
        $this->assertDatabaseCount('trades', 2);
        $this->assertDatabaseHas('trades', [
            'side' => 'buy',
            'quantity' => 100,
            'price' => 15.5,
        ]);
        $this->assertDatabaseHas('trades', [
            'side' => 'sell',
            'quantity' => 50,
            'price' => 20.0,
        ]);

        // Assert that new stock was created (check by name since code depends on API)
        $this->assertDatabaseHas('stocks', [
            'name' => 'New Stock',
        ]);
    }

    #[Test]
    public function test_restore_skips_duplicate_trades(): void
    {
        $stock = Stock::factory()->create([
            'code' => 'sh601166',
            'name' => 'Industrial Bank',
        ]);

        // Create existing trade with exact datetime that will be in backup
        $executionTime = now()->subDays(5)->startOfSecond();
        Trade::factory()->create([
            'stock_id' => $stock->id,
            'side' => 'buy',
            'quantity' => 100,
            'price' => 15.5,
            'executed_at' => $executionTime,
        ]);

        // Verify we have 1 trade before restore
        $this->assertDatabaseCount('trades', 1);

        $backupData = [
            'export_date' => now()->toIso8601String(),
            'total_trades' => 2,
            'trades' => [
                [
                    'stock_code' => '601166',
                    'stock_name' => 'Industrial Bank',
                    'side' => 'buy',
                    'quantity' => 100,
                    'price' => 15.5,
                    'executed_at' => $executionTime->toDateTimeString(),
                ],
                [
                    'stock_code' => '601166',
                    'stock_name' => 'Industrial Bank',
                    'side' => 'sell',
                    'quantity' => 50,
                    'price' => 20.0,
                    'executed_at' => now()->subDays(2)->toDateTimeString(),
                ],
            ],
        ];

        $jsonContent = json_encode($backupData, JSON_PRETTY_PRINT);
        $file = UploadedFile::fake()->createWithContent('backup.json', $jsonContent);

        Livewire::test(ManageTrades::class)
            ->callAction('restore', [
                'backup_file' => [$file],
            ])
            ->assertHasNoErrors();

        // Should have 2 trades total (1 existing + 1 new)
        $this->assertDatabaseCount('trades', 2);
        $this->assertDatabaseHas('trades', [
            'side' => 'sell',
            'quantity' => 50,
        ]);
    }

    #[Test]
    public function test_restore_validates_json_format(): void
    {
        $invalidJson = 'not valid json';
        $file = UploadedFile::fake()->createWithContent('invalid.json', $invalidJson);

        Livewire::test(ManageTrades::class)
            ->callAction('restore', [
                'backup_file' => [$file],
            ])
            ->assertHasNoErrors();

        // No trades should be imported
        $this->assertDatabaseCount('trades', 0);
    }

    #[Test]
    public function test_restore_validates_trades_array(): void
    {
        $invalidData = [
            'export_date' => now()->toIso8601String(),
            'total_trades' => 0,
            'no_trades_here' => [],
        ];

        $jsonContent = json_encode($invalidData);
        $file = UploadedFile::fake()->createWithContent('invalid.json', $jsonContent);

        Livewire::test(ManageTrades::class)
            ->callAction('restore', [
                'backup_file' => [$file],
            ])
            ->assertHasNoErrors();

        // No trades should be imported
        $this->assertDatabaseCount('trades', 0);
    }
}
