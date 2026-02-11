<?php

namespace App\Observers;

use App\Models\Holding;
use App\Models\Trade;

class HoldingObserver
{
    public function updated(Holding $holding): void
    {
        $this->recalculateIfInitialValuesChanged($holding);
    }

    protected function recalculateIfInitialValuesChanged(Holding $holding): void
    {
        if (! $holding->isDirty(['initial_quantity', 'initial_cost'])) {
            return;
        }

        $trades = Trade::where('stock_id', $holding->stock_id)->get();

        $quantity = $holding->initial_quantity;
        $totalCost = $holding->initial_quantity * $holding->initial_cost;

        foreach ($trades as $trade) {
            switch ($trade->type) {
                case 'buy':
                    $quantity += $trade->quantity;
                    $totalCost += $trade->quantity * $trade->price;

                    break;

                case 'sell':
                    $quantity -= $trade->quantity;
                    $totalCost -= $trade->quantity * $trade->price;

                    break;

                case 'dividend':
                    // Dividend doesn't affect holdings
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

        $holding->updateQuietly([
            'initial_quantity' => $holding->initial_quantity,
            'initial_cost' => $holding->initial_cost,
            'quantity' => $quantity,
            'total_cost' => $totalCost,
            'average_cost' => $averageCost,
        ]);
    }
}
