<?php

namespace Tests\Feature;

use App\Models\DayPrice;
use App\Models\PriceAlert;
use App\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckPriceAlertsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_triggered_when_price_rises_above_threshold()
    {
        // Set Bark URL via AppSetting
        \App\Models\AppSetting::set('bark_url', 'https://test.bark.com/push');

        $stock = Stock::factory()->create();
        $alert = PriceAlert::factory()->create([
            'stock_id' => $stock->id,
            'threshold_type' => 'rise',
            'threshold_value' => 100.00,
            'is_active' => true,
            'last_notified_at' => null,
        ]);

        DayPrice::factory()->create([
            'stock_id' => $stock->id,
            'date' => now()->format('Y-m-d'),
            'close_price' => 105.00,
        ]);

        // Fake HTTP request to Bark
        Http::fake([
            'https://test.bark.com/push/*' => Http::response(['code' => 200], 200),
        ]);

        $exitCode = Artisan::call('app:check-price-alerts');

        $this->assertEquals(0, $exitCode);
        $alert->refresh();
        $this->assertNotNull($alert->last_notified_at);
    }

    public function test_alert_triggered_when_price_drops_below_threshold()
    {
        $stock = Stock::factory()->create();
        $alert = PriceAlert::factory()->create([
            'stock_id' => $stock->id,
            'threshold_type' => 'drop',
            'threshold_value' => 100.00,
            'is_active' => true,
            'last_notified_at' => null,
        ]);

        DayPrice::factory()->create([
            'stock_id' => $stock->id,
            'date' => now()->format('Y-m-d'),
            'close_price' => 95.00,
        ]);

        // Fake HTTP request to Bark
        Http::fake([
            'https://test.bark.com/push/*' => Http::response(['code' => 200], 200),
        ]);

        // Set Bark URL via AppSetting
        \App\Models\AppSetting::set('bark_url', 'https://test.bark.com/push');

        $exitCode = Artisan::call('app:check-price-alerts');

        $this->assertEquals(0, $exitCode);
        $alert->refresh();
        $this->assertNotNull($alert->last_notified_at);
    }

    public function test_no_alert_when_already_notified_today()
    {
        $stock = Stock::factory()->create();
        $alert = PriceAlert::factory()->create([
            'stock_id' => $stock->id,
            'threshold_type' => 'rise',
            'threshold_value' => 100.00,
            'is_active' => true,
            'last_notified_at' => now(),
        ]);

        DayPrice::factory()->create([
            'stock_id' => $stock->id,
            'date' => now()->format('Y-m-d'),
            'close_price' => 105.00,
        ]);

        Http::fake();

        $exitCode = Artisan::call('app:check-price-alerts');

        $this->assertEquals(0, $exitCode);
        $alert->refresh();
        // last_notified_at should not be updated
        $this->assertTrue($alert->last_notified_at->isToday());
    }

    public function test_no_alert_when_price_hasnt_crossed_threshold()
    {
        $stock = Stock::factory()->create();
        $alert = PriceAlert::factory()->create([
            'stock_id' => $stock->id,
            'threshold_type' => 'rise',
            'threshold_value' => 100.00,
            'is_active' => true,
            'last_notified_at' => null,
        ]);

        DayPrice::factory()->create([
            'stock_id' => $stock->id,
            'date' => now()->format('Y-m-d'),
            'close_price' => 95.00,
        ]);

        Http::fake();

        $exitCode = Artisan::call('app:check-price-alerts');

        $this->assertEquals(0, $exitCode);
        $alert->refresh();
        $this->assertNull($alert->last_notified_at);
    }

    public function test_no_alert_when_alert_is_inactive()
    {
        $stock = Stock::factory()->create();
        $alert = PriceAlert::factory()->create([
            'stock_id' => $stock->id,
            'threshold_type' => 'rise',
            'threshold_value' => 100.00,
            'is_active' => false,
            'last_notified_at' => null,
        ]);

        DayPrice::factory()->create([
            'stock_id' => $stock->id,
            'date' => now()->format('Y-m-d'),
            'close_price' => 105.00,
        ]);

        Http::fake();

        $exitCode = Artisan::call('app:check-price-alerts');

        $this->assertEquals(0, $exitCode);
        $alert->refresh();
        $this->assertNull($alert->last_notified_at);
    }

    public function test_multiple_alerts_for_same_stock()
    {
        $stock = Stock::factory()->create();
        $alert1 = PriceAlert::factory()->create([
            'stock_id' => $stock->id,
            'threshold_type' => 'rise',
            'threshold_value' => 100.00,
            'is_active' => true,
            'last_notified_at' => null,
        ]);
        $alert2 = PriceAlert::factory()->create([
            'stock_id' => $stock->id,
            'threshold_type' => 'drop',
            'threshold_value' => 90.00,
            'is_active' => true,
            'last_notified_at' => null,
        ]);

        DayPrice::factory()->create([
            'stock_id' => $stock->id,
            'date' => now()->format('Y-m-d'),
            'close_price' => 105.00,
        ]);

        // Fake HTTP request to Bark
        Http::fake([
            'https://test.bark.com/push/*' => Http::response(['code' => 200], 200),
        ]);

        // Set Bark URL via AppSetting
        \App\Models\AppSetting::set('bark_url', 'https://test.bark.com/push');

        $exitCode = Artisan::call('app:check-price-alerts');

        $this->assertEquals(0, $exitCode);

        $alert1->refresh();
        $alert2->refresh();

        $this->assertNotNull($alert1->last_notified_at);
        $this->assertNull($alert2->last_notified_at);
    }

    public function test_alert_with_no_todays_price_data()
    {
        $stock = Stock::factory()->create();
        $alert = PriceAlert::factory()->create([
            'stock_id' => $stock->id,
            'threshold_type' => 'rise',
            'threshold_value' => 100.00,
            'is_active' => true,
            'last_notified_at' => null,
        ]);

        // Create a price from yesterday, not today
        DayPrice::factory()->create([
            'stock_id' => $stock->id,
            'date' => now()->subDay()->format('Y-m-d'),
            'close_price' => 105.00,
        ]);

        Http::fake();

        $exitCode = Artisan::call('app:check-price-alerts');

        $this->assertEquals(0, $exitCode);
        $alert->refresh();
        $this->assertNull($alert->last_notified_at);
    }
}
