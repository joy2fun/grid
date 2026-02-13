<?php

namespace App\Console\Commands;

use App\Models\Holding;
use App\Models\Trade;
use Illuminate\Console\Command;

class RecalculateHoldingCosts extends Command
{
    protected $signature = 'app:recalculate-holding-costs';

    protected $description = 'Recalculate all holdings cost based on trade records';

    public function handle(): int
    {
        $holdings = Holding::all();

        $this->withProgressBar($holdings, function ($holding) {
            $this->recalculateHolding($holding);
        });

        $this->newLine();
        $this->info("Recalculated {$holdings->count()} holdings.");

        return Command::SUCCESS;
    }

    protected function recalculateHolding(Holding $holding): void
    {
        $stockId = $holding->stock_id;

        $trades = Trade::where('stock_id', $stockId)
            ->orderBy('executed_at')
            ->orderBy('id')
            ->get();

        $initialQuantity = $holding->initial_quantity ?? 0;
        $initialCost = $holding->initial_cost ?? 0;

        $quantity = (float) $initialQuantity;
        $totalCost = (float) ($initialQuantity * $initialCost);

        foreach ($trades as $trade) {
            switch ($trade->type) {
                case 'buy':
                    $quantity += (float) $trade->quantity;
                    $totalCost += (float) $trade->quantity * (float) $trade->price;

                    break;

                case 'sell':
                    $quantity -= (float) $trade->quantity;
                    $totalCost -= (float) $trade->quantity * (float) $trade->price;

                    break;

                case 'dividend':
                    $dividendAmount = (float) $trade->quantity * (float) $trade->price;
                    if ($dividendAmount > 0) {
                        $totalCost -= $dividendAmount;
                    }

                    break;

                case 'stock_split':
                    $ratio = (float) ($trade->split_ratio ?? 1);
                    if ($ratio > 0) {
                        $quantity = $quantity * $ratio;
                    }

                    break;

                case 'stock_dividend':
                    $ratio = (float) ($trade->split_ratio ?? 0);
                    if ($ratio > 0) {
                        $additionalShares = $quantity * $ratio;
                        $quantity += $additionalShares;
                    }

                    break;
            }
        }

        $averageCost = $quantity > 0 ? $totalCost / $quantity : 0;

        $holding->update([
            'quantity' => $quantity,
            'total_cost' => $totalCost,
            'average_cost' => $averageCost,
        ]);
    }
}
