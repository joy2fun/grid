<?php

namespace App\Services;

use App\Models\AppSetting;
use Exception;
use Illuminate\Support\Facades\Http;

class BarkService
{
    public static function send(string $title, string $body, ?string $url = null): bool
    {
        $barkUrl = $url ?? config('services.bark.url') ?? AppSetting::get('bark_url');

        if (empty($barkUrl)) {
            return false;
        }

        // Ensure URL ends with slash
        $barkUrl = rtrim($barkUrl, '/') . '/';

        try {
            $response = Http::get($barkUrl . urlencode($title) . '/' . urlencode($body));
            return $response->successful();
        } catch (Exception $e) {
            logger()->error('Failed to send Bark notification: ' . $e->getMessage());
            return false;
        }
    }
}