<?php

namespace App\Console\Commands;

use App\Models\Stock;
use App\Services\StockService;
use App\Utilities\StockPriceService;
use Illuminate\Console\Command;

class SyncRealtimePrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-realtime-prices {type? : The type of stocks to sync (etf|index)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync realtime stock prices for stocks';

    /**
     * Execute the console command.
     */
    public function handle(StockService $stockService): int
    {
        $type = $this->argument('type');

        if ($type) {
            $this->info("Starting realtime price sync for type: {$type}...");
            $stocks = Stock::where('type', $type)->get();
        } else {
            $this->info('Starting realtime price sync for all stocks...');
            $stocks = Stock::all();
        }

        if ($stocks->isEmpty()) {
            $this->warn('No stocks found to sync.');

            return 0;
        }

        $codes = $stocks->pluck('code')->toArray();

        // GTimg API supports multiple codes in one request, chunking to avoid URL length limits
        $chunks = array_chunk($codes, 50);

        foreach ($chunks as $chunk) {
            try {
                $this->info('Fetching prices for chunk of '.count($chunk).' stocks...');
                $realtimeData = StockPriceService::getRealtimePrices(...$chunk);

                $this->info('Updating database...');
                $stockService->updateRealtimePrices($realtimeData);
            } catch (\Exception $e) {
                $this->error('Error syncing chunk: '.$e->getMessage());
            }
        }

        $this->info('Realtime price sync completed.');

        return 0;
    }
}
