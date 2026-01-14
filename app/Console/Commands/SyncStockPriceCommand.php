<?php

namespace App\Console\Commands;

use App\Models\Stock;
use App\Services\StockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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
    public function handle(StockService $stockService)
    {
        $stockCode = $this->argument('stock_code');

        $this->info("Starting to sync prices for stock: {$stockCode}");

        try {
            $result = $stockService->syncPriceByStockCode($stockCode);

            if ($result['success']) {
                $this->info("Successfully synced {$result['processed_count']} price records for {$stockCode}");

                return 0;
            } else {
                $this->error("Failed to sync stock prices for {$stockCode}");

                return 1;
            }
        } catch (\Exception $e) {
            $this->error("Error syncing stock prices for {$stockCode}: ".$e->getMessage());

            return 1;
        }
    }
}
