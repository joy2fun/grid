<?php

namespace App\Console\Commands;

use App\Models\Stock;
use Illuminate\Console\Command;

class RefreshStockLastTradeCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:refresh-last-trade-cache
                            {stock? : Stock code to refresh (optional, refreshes all if omitted)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh last_trade_at and last_trade_price cache columns';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $stockCode = $this->argument('stock');

        if ($stockCode) {
            $stock = Stock::where('code', $stockCode)->first();

            if (! $stock) {
                $this->error("Stock with code '{$stockCode}' not found.");

                return self::FAILURE;
            }

            $this->refreshStock($stock);
            $this->info("Refreshed cache for stock: {$stock->code}");
        } else {
            $count = 0;
            // Process stocks with trades
            Stock::query()
                ->whereHas('trades', fn ($q) => $q->whereIn('type', ['buy', 'sell']))
                ->chunk(100, function ($stocks) use (&$count) {
                    foreach ($stocks as $stock) {
                        $this->refreshStock($stock);
                        $count++;
                    }
                });

            // Also mark stocks without trades as cached (xirr = 0, no last trade)
            Stock::query()
                ->where('type', '!=', 'index')
                ->whereDoesntHave('trades', fn ($q) => $q->whereIn('type', ['buy', 'sell']))
                ->update(['xirr' => 0, 'last_trade_at' => null, 'last_trade_price' => null]);

            $this->info("Refreshed cache for {$count} stocks.");
        }

        return self::SUCCESS;
    }

    protected function refreshStock(Stock $stock): void
    {
        $lastTrade = $stock->trades()
            ->whereIn('type', ['buy', 'sell'])
            ->latest('executed_at')
            ->first();

        $xirr = $stock->calculateXirr();

        $stock->update([
            'last_trade_at' => $lastTrade?->executed_at,
            'last_trade_price' => $lastTrade?->price,
            'xirr' => $xirr ?? 0,
        ]);
    }
}
