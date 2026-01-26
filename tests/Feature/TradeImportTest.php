<?php

namespace Tests\Feature;

use App\Services\DeepSeekService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TradeImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_deepseek_parses_ocr_data_correctly(): void
    {
        $ocrData = [
            'words_result' => [
                [
                    'words' => '601166',
                    'location' => ['top' => 10, 'left' => 10, 'width' => 50, 'height' => 20],
                ],
                [
                    'words' => '100',
                    'location' => ['top' => 10, 'left' => 70, 'width' => 30, 'height' => 20],
                ],
            ],
        ];

        $mockDeepSeekResponse = [
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'trades' => [
                                [
                                    'code' => '601166',
                                    'name' => '兴业银行',
                                    'quantity' => 100,
                                    'price' => 10.5,
                                    'time' => '2026-01-01 09:30:00',
                                    'side' => 'buy',
                                ],
                            ],
                        ]),
                    ],
                ],
            ],
        ];

        Http::fake([
            'api.deepseek.com/v1/*' => Http::response($mockDeepSeekResponse, 200),
        ]);

        $deepSeekService = new DeepSeekService;
        $result = $deepSeekService->parseTradeFromOCR($ocrData);

        $this->assertNotNull($result);
        $this->assertCount(1, $result['trades']);
        $this->assertEquals('601166', $result['trades'][0]['code']);
        $this->assertEquals('兴业银行', $result['trades'][0]['name']);
        $this->assertEquals(100, $result['trades'][0]['quantity']);
    }
}
