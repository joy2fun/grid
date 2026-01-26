<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BaiduOCRService
{
    protected string $token;

    protected string $ocrUrl = 'https://aip.baidubce.com/rest/2.0/ocr/v1/general';

    public function __construct()
    {
        $this->token = \App\Models\AppSetting::get('baidu_ocr_token') ?: (config('services.baidu.ocr.token') ?? '');
    }

    /**
     * Perform OCR on an image file using direct token authentication.
     *
     * @param  string  $imagePath  Absolute path to the image file.
     * @return array|null Extracted text or null on failure.
     */
    public function ocr(string $imagePath): ?array
    {
        if (! file_exists($imagePath)) {
            Log::error("BaiduOCRService: Image file not found at {$imagePath}");

            return null;
        }

        if (! $this->token) {
            Log::error('BaiduOCRService: Token not configured');

            return null;
        }

        $imageContent = base64_encode(file_get_contents($imagePath));

        try {
            $response = Http::asForm()
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$this->token}",
                ])
                ->post($this->ocrUrl, [
                    'image' => $imageContent,
                    'detect_direction' => 'false',
                    'detect_language' => 'false',
                    'vertexes_location' => 'false',
                    'paragraph' => 'false',
                    'probability' => 'false',
                ]);

            if ($response->failed()) {
                Log::error('BaiduOCRService: OCR API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('BaiduOCRService: OCR Exception: '.$e->getMessage());

            return null;
        }
    }
}
