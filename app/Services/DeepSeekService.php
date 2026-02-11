<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeepSeekService
{
    protected string $apiKey;

    protected string $baseUrl = 'https://api.deepseek.com/v1';

    public function __construct()
    {
        $this->apiKey = \App\Models\AppSetting::get('deepseek_api_key') ?: (config('services.deepseek.key') ?? '');
    }

    /**
     * Parse structured OCR data (with positions) and return structured trade JSON.
     *
     * @param  array  $ocrData  Baidu OCR response data.
     */
    public function parseTradeFromOCR(array $ocrData): ?array
    {
        // Compact the OCR data to just relevant words and their locations
        $compactData = array_map(function ($item) {
            return [
                'words' => $item['words'],
                'location' => $item['location'],
            ];
        }, $ocrData['words_result'] ?? []);

        $prompt = <<<'PROMPT'
You are a trading data extraction assistant. Parse the provided OCR data (which includes words and their pixel positions) and extract a list of trade records.

The output MUST be a valid JSON object with a "trades" key containing an array of trade objects.
Each trade object MUST have the following fields:
- "code": The stock code (e.g., "601166" or "000001").
- "name": The stock name (e.g., "兴业银行" or "平安银行").
- "type": The transaction type - one of: "buy" (买入), "sell" (卖出), "dividend" (现金分红), "stock_dividend" (送股/转增股), "stock_split" (股票分割/合并).
- "quantity": For buy/sell/dividend: number of shares. For stock_dividend: base share count (e.g., if 10送3, this is 10). For stock_split: not required.
- "price": For buy/sell: execution price. For dividend: dividend amount per share. For stock_dividend: ratio (e.g., 0.3 for 10送3). For stock_split: ratio.
- "time": The execution time in "YYYY-MM-DD HH:MM:SS" format.

Examples:
- Buy 1000 shares at ¥25.50: type="buy", quantity=1000, price=25.50
- Cash dividend of ¥0.5 per share with 1000 shares: type="dividend", quantity=1000, price=0.5
- Stock dividend 10送3 (10 shares get 3 bonus): type="stock_dividend", quantity=10, price=0.3
- Stock split 1:2 (1 share becomes 2): type="stock_split", price=2.0

If any field is missing, try to infer it or leave it null if impossible.
ONLY return the JSON object, no other text or explanation.
PROMPT;

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/chat/completions", [
                'model' => 'deepseek-chat',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $prompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => "OCR parsed result with words position: \n\n".json_encode($compactData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                ],
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object'],
            ]);

            if ($response->failed()) {
                Log::error('DeepSeekService API request failed (OCR data)', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $content = $response->json('choices.0.message.content');

            return json_decode($content, true);
        } catch (\Exception $e) {
            Log::error('DeepSeekService Exception (OCR data): '.$e->getMessage());

            return null;
        }
    }
}
