<?php

namespace Tests\Unit;

use App\Models\PriceAlert;
use App\Models\Stock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_relationship()
    {
        $stock = Stock::factory()->create();
        $alert = PriceAlert::factory()->create(['stock_id' => $stock->id]);

        $this->assertInstanceOf(Stock::class, $alert->stock);
        $this->assertEquals($stock->id, $alert->stock->id);
    }

    public function test_is_due_for_notification_returns_true_when_never_notified()
    {
        $alert = PriceAlert::factory()->create(['last_notified_at' => null]);

        $this->assertTrue($alert->isDueForNotification());
    }

    public function test_is_due_for_notification_returns_false_when_notified_today()
    {
        $alert = PriceAlert::factory()->create([
            'last_notified_at' => Carbon::now(),
        ]);

        $this->assertFalse($alert->isDueForNotification());
    }

    public function test_is_due_for_notification_returns_true_when_notified_yesterday()
    {
        $alert = PriceAlert::factory()->create([
            'last_notified_at' => Carbon::yesterday(),
        ]);

        $this->assertTrue($alert->isDueForNotification());
    }

    public function test_should_trigger_for_rise_threshold_when_price_equals_threshold()
    {
        $alert = PriceAlert::factory()->create([
            'threshold_type' => 'rise',
            'threshold_value' => 100.00,
        ]);

        $this->assertTrue($alert->shouldTrigger(100.00));
    }

    public function test_should_trigger_for_rise_threshold_when_price_above_threshold()
    {
        $alert = PriceAlert::factory()->create([
            'threshold_type' => 'rise',
            'threshold_value' => 100.00,
        ]);

        $this->assertTrue($alert->shouldTrigger(105.00));
    }

    public function test_should_not_trigger_for_rise_threshold_when_price_below_threshold()
    {
        $alert = PriceAlert::factory()->create([
            'threshold_type' => 'rise',
            'threshold_value' => 100.00,
        ]);

        $this->assertFalse($alert->shouldTrigger(99.00));
    }

    public function test_should_trigger_for_drop_threshold_when_price_equals_threshold()
    {
        $alert = PriceAlert::factory()->create([
            'threshold_type' => 'drop',
            'threshold_value' => 100.00,
        ]);

        $this->assertTrue($alert->shouldTrigger(100.00));
    }

    public function test_should_trigger_for_drop_threshold_when_price_below_threshold()
    {
        $alert = PriceAlert::factory()->create([
            'threshold_type' => 'drop',
            'threshold_value' => 100.00,
        ]);

        $this->assertTrue($alert->shouldTrigger(95.00));
    }

    public function test_should_not_trigger_for_drop_threshold_when_price_above_threshold()
    {
        $alert = PriceAlert::factory()->create([
            'threshold_type' => 'drop',
            'threshold_value' => 100.00,
        ]);

        $this->assertFalse($alert->shouldTrigger(105.00));
    }

    public function test_active_scope()
    {
        $activeAlert = PriceAlert::factory()->create(['is_active' => true]);
        $inactiveAlert = PriceAlert::factory()->create(['is_active' => false]);

        $activeAlerts = PriceAlert::active()->get();

        $this->assertCount(1, $activeAlerts);
        $this->assertEquals($activeAlert->id, $activeAlerts->first()->id);
    }
}
