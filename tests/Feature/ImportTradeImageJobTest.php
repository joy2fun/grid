<?php

namespace Tests\Feature;

use App\Jobs\ImportTradeImageJob;
use App\Models\Stock;
use App\Models\Trade;
use App\Services\BaiduOCRService;
use App\Services\DeepSeekService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportTradeImageJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_processes_image_and_creates_trades(): void
    {
        Http::fake([
            'qt.gtimg.cn/*' => Http::response('v_sh601166="兴业银行"', 200),
        ]);

        Storage::disk('public')->put('test.jpg', 'content');
        $fullPath = Storage::disk('public')->path('test.jpg');

        $ocrData = ['words_result' => [['words' => '601166']]];
        $deepSeekData = [
            'trades' => [
                [
                    'code' => '601166',
                    'name' => '兴业银行',
                    'quantity' => 100,
                    'price' => 10.5,
                    'time' => '2026-01-01 09:30:00',
                    'type' => 'buy',
                ],
            ],
        ];

        $baiduMock = $this->mock(BaiduOCRService::class);
        $baiduMock->shouldReceive('ocr')->with($fullPath)->andReturn($ocrData);

        $deepSeekMock = $this->mock(DeepSeekService::class);
        $deepSeekMock->shouldReceive('parseTradeFromOCR')->with($ocrData)->andReturn($deepSeekData);

        $job = new ImportTradeImageJob('test.jpg', '601166');
        $job->handle($baiduMock, $deepSeekMock, app(StockService::class));

        $this->assertDatabaseHas('stocks', ['code' => 'sh601166']);
        $this->assertDatabaseHas('trades', [
            'quantity' => 100,
            'price' => 10.5,
            'type' => 'buy',
            'executed_at' => '2026-01-01 09:30:00',
        ]);
    }

    public function test_job_handles_duplicates(): void
    {
        Http::fake([
            'qt.gtimg.cn/*' => Http::response('v_sh601166="兴业银行"', 200),
        ]);

        $stock = Stock::create(['code' => 'sh601166', 'name' => '兴业银行']);
        Trade::create([
            'stock_id' => $stock->id,
            'type' => 'buy',
            'quantity' => 100,
            'price' => 10.5,
            'executed_at' => '2026-01-01 09:30:00',
        ]);

        Storage::disk('public')->put('test.jpg', 'content');
        $fullPath = Storage::disk('public')->path('test.jpg');

        $ocrData = ['words_result' => [['words' => '601166']]];
        $deepSeekData = [
            'trades' => [
                [
                    'code' => '601166',
                    'name' => '兴业银行',
                    'quantity' => 100,
                    'price' => 10.5,
                    'time' => '2026-01-01 09:30:00',
                    'type' => 'buy',
                ],
            ],
        ];

        $baiduMock = $this->mock(BaiduOCRService::class);
        $baiduMock->shouldReceive('ocr')->andReturn($ocrData);

        $deepSeekMock = $this->mock(DeepSeekService::class);
        $deepSeekMock->shouldReceive('parseTradeFromOCR')->andReturn($deepSeekData);

        $job = new ImportTradeImageJob('test.jpg');
        $job->handle($baiduMock, $deepSeekMock, app(StockService::class));

        // Still only 1 trade
        $this->assertEquals(1, Trade::count());
    }
}
