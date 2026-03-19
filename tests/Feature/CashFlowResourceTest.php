<?php

namespace Tests\Feature;

use App\Filament\Resources\CashFlows\CashFlowResource;
use App\Filament\Resources\CashFlows\Pages\ManageCashFlows;
use App\Models\CashFlow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CashFlowResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_render_cash_flow_index_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(CashFlowResource::getUrl('index'))
            ->assertSuccessful();
    }

    public function test_can_list_cash_flows(): void
    {
        $user = User::factory()->create();
        $cashFlows = CashFlow::factory()->count(3)->create();

        Livewire::actingAs($user)
            ->test(ManageCashFlows::class)
            ->assertCanSeeTableRecords($cashFlows);
    }

    public function test_can_create_cash_flow(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ManageCashFlows::class)
            ->callAction('create', [
                'date' => '2025-06-15',
                'amount' => -10000.50,
                'notes' => 'Initial investment',
            ])
            ->assertNotified();

        $this->assertDatabaseHas('cash_flows', [
            'date' => '2025-06-15 00:00:00',
            'amount' => -10000.50,
            'notes' => 'Initial investment',
        ]);
    }

    public function test_can_edit_cash_flow(): void
    {
        $user = User::factory()->create();
        $cashFlow = CashFlow::factory()->create([
            'date' => '2025-01-01',
            'amount' => -5000,
            'notes' => 'Original note',
        ]);

        Livewire::actingAs($user)
            ->test(ManageCashFlows::class)
            ->callTableAction('edit', $cashFlow, [
                'date' => '2025-01-02',
                'amount' => -6000,
                'notes' => 'Updated note',
            ])
            ->assertNotified();

        $this->assertDatabaseHas('cash_flows', [
            'id' => $cashFlow->id,
            'date' => '2025-01-02 00:00:00',
            'amount' => -6000,
            'notes' => 'Updated note',
        ]);
    }

    public function test_can_delete_cash_flow(): void
    {
        $user = User::factory()->create();
        $cashFlow = CashFlow::factory()->create();

        Livewire::actingAs($user)
            ->test(ManageCashFlows::class)
            ->callTableAction('delete', $cashFlow)
            ->assertNotified();

        $this->assertDatabaseMissing('cash_flows', [
            'id' => $cashFlow->id,
        ]);
    }

    public function test_can_import_csv(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $csvContent = "date,amount,notes\n2025-01-01,-10000,Initial investment\n2025-03-15,500,Dividend\n2025-06-01,-5000,Additional buy\n";

        $file = UploadedFile::fake()->createWithContent('cashflows.csv', $csvContent);

        Livewire::actingAs($user)
            ->test(ManageCashFlows::class)
            ->callAction('importCsv', [
                'csv_file' => $file,
            ])
            ->assertNotified();

        $this->assertDatabaseCount('cash_flows', 3);
        $this->assertDatabaseHas('cash_flows', [
            'date' => '2025-01-01 00:00:00',
            'amount' => -10000,
            'notes' => 'Initial investment',
        ]);
        $this->assertDatabaseHas('cash_flows', [
            'date' => '2025-03-15 00:00:00',
            'amount' => 500,
            'notes' => 'Dividend',
        ]);
    }

    public function test_can_calculate_xirr(): void
    {
        $user = User::factory()->create();

        // Create cash flows: invest 10000, get back 11000 after ~1 year
        CashFlow::factory()->create([
            'date' => '2025-01-01',
            'amount' => -10000,
        ]);

        CashFlow::factory()->create([
            'date' => '2025-06-01',
            'amount' => -5000,
        ]);

        Livewire::actingAs($user)
            ->test(ManageCashFlows::class)
            ->callAction('calculateXirr', [
                'portfolio_value' => 16000,
            ])
            ->assertNotified();
    }

    public function test_xirr_fails_with_no_cash_flows(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ManageCashFlows::class)
            ->callAction('calculateXirr', [
                'portfolio_value' => 10000,
            ])
            ->assertNotified();

        $this->assertDatabaseCount('cash_flows', 0);
    }

    public function test_create_requires_date_and_amount(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ManageCashFlows::class)
            ->callAction('create', [
                'date' => null,
                'amount' => null,
            ])
            ->assertHasActionErrors([
                'date' => 'required',
                'amount' => 'required',
            ]);
    }
}
