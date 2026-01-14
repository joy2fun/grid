<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\DayPrice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Sync stock prices by stock code from the provided API
     *
     * @param string $stockCode The stock code (e.g., 'sh601166')
     * @return bool True if sync was successful, false otherwise
     */
    public function syncPriceByStockCode(string $stockCode): bool
    {
        // Construct the API URL
        $url = "https://web.ifzq.gtimg.cn/appstock/app/fqkline/get?param={$stockCode},day,,,2000,qfq";

        try {
            // Fetch data from the API
            $response = Http::get($url);

            if (!$response->successful()) {
                \Log::error("Failed to fetch stock data for {$stockCode}", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return false;
            }

            $data = $response->json();

            if (!isset($data['data'][$stockCode]['qfqday'])) {
                \Log::error("Invalid response format for stock {$stockCode}", ['data' => $data]);
                return false;
            }

            $prices = $data['data'][$stockCode]['qfqday'];

            // Find or create the stock
            $stock = Stock::firstOrCreate(
                ['code' => $stockCode],
                ['name' => $this->extractStockName($data, $stockCode)]
            );

            // Prepare data for bulk insert/update
            $dayPricesData = [];
            foreach ($prices as $priceData) {
                if (count($priceData) >= 6) {
                    [$date, $openPrice, $closePrice, $highPrice, $lowPrice, $volume] = $priceData;

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
                return true; // Nothing to sync
            }

            // Process in chunks to improve performance
            $chunks = array_chunk($dayPricesData, 100);

            foreach ($chunks as $chunk) {
                $this->bulkUpsertDayPrices($chunk);
            }

            return true;
        } catch (\Exception $e) {
            \Log::error("Error syncing stock prices for {$stockCode}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Bulk upsert day prices to handle duplicates efficiently
     *
     * @param array $dayPricesData
     * @return void
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

        $sql = "
            INSERT OR REPLACE INTO day_prices (stock_id, date, open_price, close_price, high_price, low_price, volume)
            VALUES " . implode(',', $values);

        DB::statement($sql, $bindings);
    }

    /**
     * Extract stock name from API response
     *
     * @param array $data The API response data
     * @param string $stockCode The stock code
     * @return string The stock name
     */
    private function extractStockName(array $data, string $stockCode): string
    {
        if (isset($data['data'][$stockCode]['qt'][$stockCode][1])) {
            return $data['data'][$stockCode]['qt'][$stockCode][1];
        }

        return 'Unknown';
    }
}