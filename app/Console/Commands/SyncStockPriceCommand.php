<?php

namespace App\Console\Commands;

use App\Models\Stock;
use App\Models\DayPrice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class SyncStockPriceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-stock-price {stock_code : The stock code to sync prices for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync stock prices by stock code from the API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $stockCode = $this->argument('stock_code');

        $this->info("Starting to sync prices for stock: {$stockCode}");

        try {
            // Construct the API URL
            $url = "https://web.ifzq.gtimg.cn/appstock/app/fqkline/get?param={$stockCode},day,,,2000,qfq";

            // Fetch data from the API
            $response = Http::get($url);

            if (!$response->successful()) {
                $this->error("Failed to fetch stock data for {$stockCode}");
                $this->error("Status: " . $response->status());
                $this->error("Body: " . $response->body());
                return 1;
            }

            $data = $response->json();

            if (!isset($data['data'][$stockCode]['qfqday'])) {
                $this->error("Invalid response format for stock {$stockCode}");
                return 1;
            }

            $prices = $data['data'][$stockCode]['qfqday'];
            $this->info("Found " . count($prices) . " price records to sync");

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
                    ];
                }
            }

            if (empty($dayPricesData)) {
                $this->info("No price data to sync for {$stockCode}");
                return 0;
            }

            // Process in chunks to improve performance
            $chunks = array_chunk($dayPricesData, 100);
            $processedCount = 0;

            foreach ($chunks as $chunk) {
                $this->bulkUpsertDayPrices($chunk);
                $processedCount += count($chunk);

                // Show progress
                $this->output->write('.');
            }

            $this->newLine();
            $this->info("Successfully synced {$processedCount} price records for {$stockCode}");

            return 0;
        } catch (\Exception $e) {
            $this->error("Error syncing stock prices for {$stockCode}: " . $e->getMessage());
            return 1;
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
