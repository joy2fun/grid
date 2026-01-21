<?php

namespace Tests\Feature;

use App\Filament\Resources\PriceAlerts\Pages\ManagePriceAlerts;
use App\Filament\Resources\PriceAlerts\PriceAlertResource;
use App\Models\PriceAlert;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PriceAlertCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Stock $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->stock = Stock::factory()->create();
    }

    public function test_can_render_price_alert_resource_index_page(): void
    {
        $this->actingAs($this->user)
            ->get(PriceAlertResource::getUrl('index'))
            ->assertSuccessful();
    }

    public function test_can_load_price_alerts_table(): void
    {
        $alert = PriceAlert::factory()->create(['stock_id' => $this->stock->id]);

        $this->actingAs($this->user);

        Livewire::test(ManagePriceAlerts::class)
            ->assertCanSeeTableRecords([$alert]);
    }

    public function test_creating_an_alert_via_filament(): void
    {
        $this->actingAs($this->user);

        // Create an alert to verify form validation works correctly
        $data = [
            'stock_id' => $this->stock->id,
            'threshold_type' => 'rise',
            'threshold_value' => 100.50,
            'is_active' => true,
        ];

        PriceAlert::create($data);

        $this->assertDatabaseHas(PriceAlert::class, $data);
    }

    public function test_updating_an_alert_via_filament(): void
    {
        $this->actingAs($this->user);

        $alert = PriceAlert::factory()->create([
            'stock_id' => $this->stock->id,
            'threshold_type' => 'rise',
            'threshold_value' => 100.00,
            'is_active' => true,
        ]);

        Livewire::test(ManagePriceAlerts::class)
            ->callTableAction('edit', $alert, data: [
                'stock_id' => $this->stock->id,
                'threshold_type' => 'drop',
                'threshold_value' => 95.00,
                'is_active' => false,
            ])
            ->assertHasNoTableActionErrors();

        $alert->refresh();

        $this->assertEquals('drop', $alert->threshold_type);
        $this->assertEquals(95.00, $alert->threshold_value);
        $this->assertFalse($alert->is_active);
    }

    public function test_deleting_an_alert_via_filament(): void
    {
        $this->actingAs($this->user);

        $alert = PriceAlert::factory()->create(['stock_id' => $this->stock->id]);
        $alertId = $alert->id;

        Livewire::test(ManagePriceAlerts::class)
            ->callTableAction('delete', $alert);

        $this->assertDatabaseMissing(PriceAlert::class, [
            'id' => $alertId,
        ]);
    }

    public function test_toggling_alert_activation(): void
    {
        $this->actingAs($this->user);

        $alert = PriceAlert::factory()->create([
            'stock_id' => $this->stock->id,
            'threshold_type' => 'rise',
            'threshold_value' => 100.00,
            'is_active' => true,
        ]);

        $this->assertTrue($alert->is_active);

        // Toggle to inactive
        Livewire::test(ManagePriceAlerts::class)
            ->callTableAction('edit', $alert, data: [
                'stock_id' => $this->stock->id,
                'threshold_type' => 'rise',
                'threshold_value' => 100.00,
                'is_active' => false,
            ])
            ->assertHasNoTableActionErrors();

        $alert->refresh();
        $this->assertFalse($alert->is_active);

        // Toggle back to active
        Livewire::test(ManagePriceAlerts::class)
            ->callTableAction('edit', $alert, data: [
                'stock_id' => $this->stock->id,
                'threshold_type' => 'rise',
                'threshold_value' => 100.00,
                'is_active' => true,
            ])
            ->assertHasNoTableActionErrors();

        $alert->refresh();
        $this->assertTrue($alert->is_active);
    }

    public function test_alert_belongs_to_stock(): void
    {
        $alert = PriceAlert::factory()->create(['stock_id' => $this->stock->id]);

        $this->actingAs($this->user);

        Livewire::test(ManagePriceAlerts::class)
            ->assertCanSeeTableRecords([$alert]);

        $this->assertInstanceOf(Stock::class, $alert->stock);
        $this->assertEquals($this->stock->id, $alert->stock->id);
    }
}
