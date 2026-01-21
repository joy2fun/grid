<?php

namespace Tests\Feature;

use App\Filament\Resources\PriceAlerts\Pages\ManagePriceAlerts;
use App\Filament\Resources\PriceAlerts\PriceAlertResource;
use App\Models\PriceAlert;
use App\Models\Stock;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
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

        Filament::setCurrentPanel('admin');
    }

    public function test_can_render_price_alert_resource_index_page(): void
    {
        $this->actingAs($this->user)
            ->get(PriceAlertResource::getUrl('index'))
            ->assertSuccessful();
    }

    public function test_price_alerts_page_displays_livewire_component(): void
    {
        $this->actingAs($this->user)
            ->get(PriceAlertResource::getUrl('index'))
            ->assertSeeLivewire(ManagePriceAlerts::class);
    }

    public function test_can_view_price_alerts_in_table(): void
    {
        $alert = PriceAlert::factory()->create(['stock_id' => $this->stock->id]);

        Livewire::actingAs($this->user)
            ->test(ManagePriceAlerts::class)
            ->assertCanSeeTableRecords([$alert]);
    }

    public function test_can_create_price_alert_via_filament(): void
    {
        $data = [
            'stock_id' => $this->stock->id,
            'threshold_type' => 'rise',
            'threshold_value' => 100.50,
            'is_active' => true,
        ];

        Livewire::actingAs($this->user)
            ->test(ManagePriceAlerts::class)
            ->callAction('create', data: $data)
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(PriceAlert::class, $data);
    }

    public function test_can_edit_price_alert_via_filament(): void
    {
        $alert = PriceAlert::factory()->create([
            'stock_id' => $this->stock->id,
            'threshold_type' => 'rise',
            'threshold_value' => 100.00,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->user)
            ->test(ManagePriceAlerts::class)
            ->callAction(TestAction::make('edit')->table($alert), data: [
                'stock_id' => $this->stock->id,
                'threshold_type' => 'drop',
                'threshold_value' => 95.00,
                'is_active' => false,
            ])
            ->assertHasNoFormErrors();

        $alert->refresh();

        $this->assertEquals('drop', $alert->threshold_type);
        $this->assertEquals(95.00, $alert->threshold_value);
        $this->assertFalse($alert->is_active);
    }

    public function test_can_delete_price_alert_via_filament(): void
    {
        $alert = PriceAlert::factory()->create(['stock_id' => $this->stock->id]);
        $alertId = $alert->id;

        Livewire::actingAs($this->user)
            ->test(ManagePriceAlerts::class)
            ->callAction(TestAction::make('delete')->table($alert));

        $this->assertDatabaseMissing(PriceAlert::class, [
            'id' => $alertId,
        ]);
    }

    public function test_can_toggle_alert_activation_via_edit(): void
    {
        $alert = PriceAlert::factory()->create([
            'stock_id' => $this->stock->id,
            'threshold_type' => 'rise',
            'threshold_value' => 100.00,
            'is_active' => true,
        ]);

        $this->assertTrue($alert->is_active);

        // Toggle to inactive
        Livewire::actingAs($this->user)
            ->test(ManagePriceAlerts::class)
            ->callAction(TestAction::make('edit')->table($alert), data: [
                'stock_id' => $this->stock->id,
                'threshold_type' => 'rise',
                'threshold_value' => 100.00,
                'is_active' => false,
            ])
            ->assertHasNoFormErrors();

        $alert->refresh();
        $this->assertFalse($alert->is_active);

        // Toggle back to active
        Livewire::actingAs($this->user)
            ->test(ManagePriceAlerts::class)
            ->callAction(TestAction::make('edit')->table($alert), data: [
                'stock_id' => $this->stock->id,
                'threshold_type' => 'rise',
                'threshold_value' => 100.00,
                'is_active' => true,
            ])
            ->assertHasNoFormErrors();

        $alert->refresh();
        $this->assertTrue($alert->is_active);
    }

    public function test_price_alert_displays_stock_relationship(): void
    {
        $alert = PriceAlert::factory()->create(['stock_id' => $this->stock->id]);

        Livewire::actingAs($this->user)
            ->test(ManagePriceAlerts::class)
            ->assertCanSeeTableRecords([$alert]);

        $this->assertInstanceOf(Stock::class, $alert->stock);
        $this->assertEquals($this->stock->id, $alert->stock->id);
    }
}
