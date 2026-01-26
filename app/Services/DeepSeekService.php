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
- "quantity": The number of shares/units traded (integer).
- "price": The execution price (float).
- "time": The execution time in "YYYY-MM-DD HH:MM:SS" format.
- "side": Either "buy" or "sell".

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
