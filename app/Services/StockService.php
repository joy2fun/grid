<?php

namespace App\Services;

use App\Models\Stock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StockService
{
    /**
     * Sync stock prices by stock code from the provided API
     *
     * @param  string  $stockCode  The stock code (e.g., 'sh601166')
     * @param  string  $endDate  The end date (format: YYYY-MM-DD)
     * @return array Result with success status and additional info like processed count
     */
    public function syncPriceByStockCode(string $stockCode, $endDate = ''): array
    {
        // Construct the API URL, default to 2000 days of data
        $url = "https://web.ifzq.gtimg.cn/appstock/app/fqkline/get?param={$stockCode},day,,{$endDate},2000,qfq";

        try {
            // Fetch data from the API
            $response = Http::get($url);

            if (! $response->successful()) {
                Log::error("Failed to fetch stock data for {$stockCode}", [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return ['success' => false, 'processed_count' => 0];
            }

            $data = $response->json();

            $prices = $data['data'][$stockCode]['qfqday'] ?? $data['data'][$stockCode]['day'] ?? [];

            if (! $prices) {
                return ['success' => false, 'processed_count' => 0];
            }

            // Find or create the stock
            $stock = Stock::firstOrCreate(
                ['code' => $stockCode],
                ['name' => $this->extractStockName($data, $stockCode)]
            );

            // Prepare data for bulk insert/update
            $dayPricesData = [];
            $today = now()->format('Y-m-d');
            foreach ($prices as $priceData) {
                if (count($priceData) >= 6) {
                    [$date, $openPrice, $closePrice, $highPrice, $lowPrice, $volume] = $priceData;

                    // Always skip today's price as it is covered by the real-time sync command
                    if ($date === $today) {
                        continue;
                    }

                    $dayPricesData[] = [
                        'stock_id' => $stock->id,
                        'date' => $date,
                        'open_price' => $openPrice,
                        'close_price' => $closePrice,
                        'high_price' => $highPrice,
                        'low_price' => $lowPrice,
                        'volume' => $volume,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (empty($dayPricesData)) {
                return ['success' => true, 'processed_count' => 0]; // Nothing to sync
            }

            // Process in chunks to improve performance
            $chunks = array_chunk($dayPricesData, 100);
            $processedCount = 0;

            foreach ($chunks as $chunk) {
                $this->bulkUpsertDayPrices($chunk);
                $processedCount += count($chunk);
            }

            // Update peak value for the stock
            $newPeak = collect($dayPricesData)->max('high_price');
            if ($newPeak > $stock->peak_value) {
                $stock->update(['peak_value' => $newPeak]);
            }

            // Recalculate XIRR if price changed (affects holding valuation)
            $this->recalculateXirrIfNeeded($stock);

            // Get the earliest date from the fetched prices for pagination
            $earliestDate = collect($dayPricesData)->min('date');

            return [
                'success' => true,
                'processed_count' => $processedCount,
                'earliest_date' => $earliestDate,
                'total_fetched' => count($prices),
            ];
        } catch (\Exception $e) {
            Log::error("Error syncing stock prices for {$stockCode}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['success' => false, 'processed_count' => 0];
        }
    }

    /**
     * Bulk upsert day prices to handle duplicates efficiently
     */
    private function bulkUpsertDayPrices(array $dayPricesData): void
    {
        // Using raw SQL for better performance with large datasets
        // For SQLite, we use INSERT OR REPLACE
        $values = [];
        $bindings = [];

        foreach ($dayPricesData as $dayPriceData) {
            $values[] = '(?, ?, ?, ?, ?, ?, ?)';
            $bindings[] = $dayPriceData['stock_id'];
            $bindings[] = $dayPriceData['date'];
            $bindings[] = $dayPriceData['open_price'];
            $bindings[] = $dayPriceData['close_price'];
            $bindings[] = $dayPriceData['high_price'];
            $bindings[] = $dayPriceData['low_price'];
            $bindings[] = $dayPriceData['volume'];
        }

        $sql = '
            INSERT OR REPLACE INTO day_prices (stock_id, date, open_price, close_price, high_price, low_price, volume)
            VALUES '.implode(',', $values);

        DB::statement($sql, $bindings);
    }

    /**
     * Update realtime prices from the provided realtime data
     *
     * @param  array  $realtimeData  Associative array of stock data indexed by code
     */
    public function updateRealtimePrices(array $realtimeData): void
    {
        $today = now()->format('Y-m-d');
        $dayPricesData = [];
        $stockUpdates = [];

        foreach ($realtimeData as $code => $data) {
            if (! $data || $data['timestamp'] !== $today) {
                continue;
            }

            $stock = Stock::where('code', $code)->first();
            if (! $stock) {
                continue;
            }

            $dayPricesData[] = [
                'stock_id' => $stock->id,
                'date' => $today,
                'open_price' => $data['open_price'],
                'close_price' => $data['current_price'],
                'high_price' => $data['high_price'],
                'low_price' => $data['low_price'],
                'volume' => $data['volume'] ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Prepare stock updates including current_price
            $stockUpdates[$stock->id] = [
                'current_price' => $data['current_price'],
                'rise_percentage' => $data['rise_percentage'] ?? null,
                'updated_at' => now(),
            ];

            // Update peak_value if current high is higher
            if ($data['high_price'] > $stock->peak_value) {
                $stockUpdates[$stock->id]['peak_value'] = $data['high_price'];
            }
        }

        if (! empty($dayPricesData)) {
            $this->bulkUpsertDayPrices($dayPricesData);
        }

        // Update stocks with current_price and potentially peak_value
        foreach ($stockUpdates as $stockId => $updates) {
            Stock::where('id', $stockId)->update($updates);

            // Recalculate XIRR for stocks with price updates
            $stock = Stock::find($stockId);
            if ($stock) {
                $this->recalculateXirrIfNeeded($stock);
            }
        }
    }

    /**
     * Recalculate XIRR for a stock if it has holdings
     * XIRR depends on current_price for the final holding valuation
     */
    private function recalculateXirrIfNeeded(Stock $stock): void
    {
        // Only recalculate for non-index stocks with trades
        if ($stock->type === 'index') {
            return;
        }

        $hasTrades = $stock->trades()
            ->whereIn('type', ['buy', 'sell'])
            ->exists();

        if (! $hasTrades) {
            return;
        }

        $xirr = $stock->calculateXirr();
        $stock->update(['xirr' => $xirr ?? 0]);
    }

    /**
     * Auto prefix stock code with sh, sz or hk
     *
     * @param  string  $code  The input stock code
     * @return string The prefixed stock code
     */
    public function autoPrefixCode(string $code): string
    {
        if (str($code)->startsWith(['sh', 'sz', 'hk'])) {
            return $code;
        }

        // Check if input is a 6-digit number
        if (preg_match('/^\d{6}$/', $code)) {
            // Query stocks table for codes ending with this number
            $stock = Stock::where('code', 'like', "%{$code}")->first();
            if ($stock) {
                return $stock->code;
            }

            // Try sh prefix
            $shCode = 'sh'.$code;
            $response = Http::get("https://qt.gtimg.cn/?q={$shCode}");
            if ($response->successful() && str_contains($response->body(), $shCode)) {
                return $shCode;
            }

            // Use sz prefix as fallback
            return 'sz'.$code;
        }

        // Not a 6-digit number, use hk prefix
        return 'hk'.$code;
    }

    /**
     * Extract stock name from API response
     *
     * @param  array  $data  The API response data
     * @param  string  $stockCode  The stock code
     * @return string The stock name
     */
    private function extractStockName(array $data, string $stockCode): string
    {
        if (isset($data['data'][$stockCode]['qt'][$stockCode][1])) {
            return $data['data'][$stockCode]['qt'][$stockCode][1];
        }

        return 'Unknown';
    }

    /**
     * Full sync stock prices by paginating through historical data
     *
     * @param  string  $stockCode  The stock code (e.g., 'sh601166')
     * @return array Result with success status, total processed count, and sync details
     */
    public function fullSyncStockPrices(string $stockCode): array
    {
        $totalProcessed = 0;
        $callCount = 0;
        $endDate = '';
        $tenYearsAgo = now()->subYears(10)->format('Y-m-d');

        while ($callCount < 100) { // Safety limit to prevent infinite loops
            $callCount++;

            $result = $this->syncPriceByStockCode($stockCode, $endDate);

            if (! $result['success']) {
                Log::warning("Full sync failed for {$stockCode} at call {$callCount}", [
                    'end_date' => $endDate,
                ]);

                break;
            }

            $totalProcessed += $result['processed_count'];

            // Stop if we fetched less than 10 records (no more historical data)
            if ($result['total_fetched'] < 10) {
                break;
            }

            // Stop if we've reached 10 years ago
            if ($result['earliest_date'] && $result['earliest_date'] <= $tenYearsAgo) {
                break;
            }

            // Use the earliest date from this batch as the next endDate
            if ($result['earliest_date']) {
                $endDate = $result['earliest_date'];
            } else {
                break;
            }

            // Sleep 1 second between API calls to avoid rate limiting
            sleep(1);
        }

        return [
            'success' => true,
            'total_processed' => $totalProcessed,
            'api_calls' => $callCount,
            'stock_code' => $stockCode,
        ];
    }
}
