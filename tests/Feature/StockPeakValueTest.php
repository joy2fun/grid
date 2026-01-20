<?php

namespace Tests\Feature;

use App\Models\Stock;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StockPeakValueTest extends TestCase
{
    use RefreshDatabase;

    public function test_peak_value_is_updated_during_historical_sync(): void
    {
        $stockCode = 'sh601166';
        Http::fake([
            '*' => Http::response([
                'data' => [
                    $stockCode => [
                        'qfqday' => [
                            ['2023-01-01', '10.00', '10.50', '11.00', '9.90', '1000'],
                            ['2023-01-02', '10.50', '11.20', '11.50', '10.40', '1100'],
                            ['2023-01-03', '11.20', '10.80', '11.30', '10.70', '900'],
                        ],
                        'qt' => [
                            $stockCode => [null, 'Sample Stock'],
                        ],
                    ],
                ],
            ]),
        ]);

        $service = new StockService;
        $service->syncPriceByStockCode($stockCode);

        $stock = Stock::where('code', $stockCode)->first();
        $this->assertEquals(11.50, (float) $stock->peak_value);
    }

    public function test_peak_value_is_updated_during_realtime_sync(): void
    {
        $stock = Stock::factory()->create([
            'code' => 'sh601166',
            'peak_value' => 10.00,
        ]);

        $today = now()->format('Y-m-d');
        $realtimeData = [
            'sh601166' => [
                'timestamp' => $today,
                'open_price' => 10.10,
                'current_price' => 10.50,
                'high_price' => 11.20,
                'low_price' => 10.00,
                'volume' => 500,
            ],
        ];

        $service = new StockService;
        $service->updateRealtimePrices($realtimeData);

        $stock->refresh();
        $this->assertEquals(11.20, (float) $stock->peak_value);
    }

    public function test_peak_value_does_not_decrease_during_sync(): void
    {
        $stock = Stock::factory()->create([
            'code' => 'sh601166',
            'peak_value' => 15.00,
        ]);

        $today = now()->format('Y-m-d');
        $realtimeData = [
            'sh601166' => [
                'timestamp' => $today,
                'open_price' => 10.10,
                'current_price' => 10.50,
                'high_price' => 11.20,
                'low_price' => 10.00,
                'volume' => 500,
            ],
        ];

        $service = new StockService;
        $service->updateRealtimePrices($realtimeData);

        $stock->refresh();
        $this->assertEquals(15.00, (float) $stock->peak_value);
    }
}
